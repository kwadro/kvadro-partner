<?php

namespace App\Controller\Api;

use App\Entity\LinkView;
use App\Repository\AdvertisingLinkRepository;
use App\Repository\LinkViewRepository;
use App\Service\Referral\ReferralPathParser;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api')]
class LinkViewApiController extends AbstractController
{
    public function __construct(
        private AdvertisingLinkRepository $advertisingLinkRepository,
        private LinkViewRepository $linkViewRepository,
        private ReferralPathParser $referralPathParser,
        private EntityManagerInterface $entityManager,
        #[Autowire(service: 'monolog.logger.api_views')]
        private LoggerInterface $logger,
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
        $rawBody = $request->getContent();
        $payload = json_decode($rawBody, true);
        if (!\is_array($payload)) {
            $payload = [];
        }

        $this->logger->info('API /api/views request', [
            'ip' => $request->getClientIp(),
            'method' => $request->getMethod(),
            'content_type' => $request->headers->get('Content-Type'),
            'origin' => $request->headers->get('Origin'),
            'referer' => $request->headers->get('Referer'),
            'user_agent' => $request->headers->get('User-Agent'),
            'raw_body' => $rawBody,
            'payload' => $payload,
            'query' => $request->query->all(),
        ]);

        $parsed = $this->resolveReferral($payload);


        if (null === $parsed) {
            $this->logger->warning('API /api/views: referral not resolved', [
                'payload' => $payload,
            ]);

            return $this->json([
                'ok' => false,
                'message' => 'Provide "key" or "url" with /{pageCode}/{linkKey}.',
            ], 400);
        }
        $this->logger->info('API /api/views $parsed ', [
            'parsed' => $parsed,
        ]);
        $advertisingLink = $this->advertisingLinkRepository->findOneByReferralKey($parsed['referralKey']);
        if (!$advertisingLink) {
            $this->logger->warning('API /api/views: advertising link not found', [
                'parsed' => $parsed,
            ]);

            return $this->json([
                'ok' => false,
                'message' => 'Advertising link not found.',
            ], 404);
        }

        $ipAddress = $request->getClientIp();
        $visitorId = $this->resolveVisitorId($payload);
        $isUnique = null === $ipAddress
            || !$this->linkViewRepository->hasViewFromIp($advertisingLink, $ipAddress);

        $view = (new LinkView())
            ->setAdvertisingLink($advertisingLink)
            ->setIpAddress($ipAddress)
            ->setVisitorId($visitorId);

        $advertisingLink->setClicks($advertisingLink->getClicks() + 1);

        $this->entityManager->persist($view);
        $this->entityManager->flush();

        $uniqueVisitors = $this->linkViewRepository->countUniqueIps($advertisingLink);

        $this->logger->info('API /api/views: view created', [
            'view_id' => $view->getId(),
            'advertising_link_id' => $advertisingLink->getId(),
            'ip_address' => $ipAddress,
            'visitor_id' => $visitorId,
            'is_unique_ip' => $isUnique,
            'unique_visitors' => $uniqueVisitors,
            'parsed' => $parsed,
        ]);

        return $this->json([
            'ok' => true,
            'view' => [
                'id' => $view->getId(),
                'advertisingLinkId' => $advertisingLink->getId(),
                'pageCode' => $parsed['pageCode'],
                'linkKey' => $parsed['linkKey'],
                'referralKey' => $parsed['referralKey'],
                'ipAddress' => $ipAddress,
                'visitorId' => $visitorId,
                'isUniqueIp' => $isUnique,
                'uniqueVisitors' => $uniqueVisitors,
                'createdAt' => $view->getCreatedAt()->format(\DateTimeInterface::ATOM),
            ],
        ], 201);
    }

    /**
     * @param array $payload
     */
    private function resolveVisitorId(array $payload): ?string
    {
        $visitorId = trim((string) ($payload['visitor_id'] ?? $payload['visitorId'] ?? ''));
        if ('' === $visitorId) {
            return null;
        }

        return mb_substr($visitorId, 0, 64);
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
