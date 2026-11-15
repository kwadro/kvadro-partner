<?php

namespace App\Controller\Api;

use App\Entity\LinkView;
use App\Repository\AdvertisingLinkRepository;
use App\Service\Referral\ReferralPathParser;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api')]
class LinkViewApiController extends AbstractController
{
    public function __construct(
        private AdvertisingLinkRepository $advertisingLinkRepository,
        private ReferralPathParser $referralPathParser,
        private EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * Accepts a referral key {pageCode}{linkKey} or a full URL with /{pageCode}/{linkKey}
     * (pageCode replaces the old fixed "pr" segment and can be any 2-char partner code).
     *
     * POST /api/views
     * Body JSON: { "key": "pb24jc3yts" } or { "url": "https://shop.example/category/x/pb/24jc3yts" }
     */
    #[Route('/views', name: 'api_link_views_create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $payload = json_decode($request->getContent(), true);
        if (!\is_array($payload)) {
            $payload = [];
        }

        $parsed = $this->resolveReferral($payload);
        if (null === $parsed) {
            return $this->json([
                'ok' => false,
                'message' => 'Provide "key" or "url" with /{pageCode}/{linkKey}.',
            ], 400);
        }

        $advertisingLink = $this->advertisingLinkRepository->findOneByReferralKey($parsed['referralKey']);
        if (!$advertisingLink) {
            return $this->json([
                'ok' => false,
                'message' => 'Advertising link not found.',
            ], 404);
        }

        $view = (new LinkView())->setAdvertisingLink($advertisingLink);
        $advertisingLink->setClicks($advertisingLink->getClicks() + 1);

        $this->entityManager->persist($view);
        $this->entityManager->flush();

        return $this->json([
            'ok' => true,
            'view' => [
                'id' => $view->getId(),
                'advertisingLinkId' => $advertisingLink->getId(),
                'pageCode' => $parsed['pageCode'],
                'linkKey' => $parsed['linkKey'],
                'referralKey' => $parsed['referralKey'],
                'createdAt' => $view->getCreatedAt()->format(\DateTimeInterface::ATOM),
            ],
        ], 201);
    }

    /**
     * @param array $payload
     *
     * @return array{pageCode: string, linkKey: string, referralKey: string}|null
     */
    private function resolveReferral(array $payload): ?array
    {
        $key = trim((string) ($payload['key'] ?? $payload['linkKey'] ?? $payload['referralKey'] ?? ''));
        if ('' !== $key) {
            $fromKey = $this->referralPathParser->parseReferralKey($key);
            if (null !== $fromKey) {
                return $fromKey;
            }
        }

        $url = trim((string) ($payload['url'] ?? ''));
        if ('' !== $url) {
            return $this->referralPathParser->parseUrl($url);
        }

        $pageCode = strtolower(trim((string) ($payload['pageCode'] ?? $payload['code'] ?? '')));
        $linkKey = trim((string) ($payload['linkKey'] ?? ''));
        if ('' !== $pageCode && '' !== $linkKey) {
            return $this->referralPathParser->parseReferralKey($pageCode.$linkKey);
        }

        return null;
    }
}
