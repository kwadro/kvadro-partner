<?php

namespace App\Controller\Api;

use App\Entity\LinkView;
use App\Repository\AdvertisingLinkRepository;
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
        private EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * Accepts a referral key {pageCode}{linkKey} (or full URL .../{pageCode}/{linkKey})
     * and stores a view for the matching AdvertisingLink.
     *
     * POST /api/views
     * Body JSON: { "key": "pb24jc3yts" } or { "url": "https://shop.example/pb/24jc3yts" }
     */
    #[Route('/views', name: 'api_link_views_create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $payload = json_decode($request->getContent(), true);
        if (!\is_array($payload)) {
            $payload = [];
        }

        $referralKey = $this->resolveReferralKey($payload);
        if (null === $referralKey) {
            return $this->json([
                'ok' => false,
                'message' => 'Provide "key" or "url" with /{pageCode}/{linkKey}.',
            ], 400);
        }

        $advertisingLink = $this->advertisingLinkRepository->findOneByReferralKey($referralKey);
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
                'createdAt' => $view->getCreatedAt()->format(\DateTimeInterface::ATOM),
            ],
        ], 201);
    }

    /**
     * @param array $payload
     */
    private function resolveReferralKey(array $payload): ?string
    {
        $key = trim((string) ($payload['key'] ?? $payload['linkKey'] ?? ''));
        if ('' !== $key) {
            return $key;
        }

        $url = trim((string) ($payload['url'] ?? ''));
        if ('' === $url) {
            return null;
        }

        $path = parse_url($url, PHP_URL_PATH);
        if (!\is_string($path)) {
            return null;
        }

        if (preg_match('#/([a-z0-9]{2})/([a-zA-Z0-9_-]{3,64})/?$#i', $path, $matches)) {
            return strtolower($matches[1]).$matches[2];
        }

        return null;
    }
}
