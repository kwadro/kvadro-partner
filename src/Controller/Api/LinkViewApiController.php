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
     * Accepts a referral link key (or full partner URL ending with /pr/{key})
     * and stores a view for the matching AdvertisingLink.
     *
     * POST /api/views
     * Body JSON: { "key": "link-key" } or { "url": "https://partner.example/pr/link-key" }
     */
    #[Route('/views', name: 'api_link_views_create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $payload = json_decode($request->getContent(), true);
        if (!\is_array($payload)) {
            $payload = [];
        }

        $linkKey = $this->resolveLinkKey($payload);
        if (null === $linkKey) {
            return $this->json([
                'ok' => false,
                'message' => 'Provide "key" or "url" with /pr/{key}.',
            ], 400);
        }

        $links = $this->advertisingLinkRepository->findByLinkKey($linkKey);
        if (0 === \count($links)) {
            return $this->json([
                'ok' => false,
                'message' => 'Advertising link not found.',
            ], 404);
        }

        if (\count($links) > 1) {
            return $this->json([
                'ok' => false,
                'message' => 'Multiple links found for this key. Send full "url".',
            ], 409);
        }

        $advertisingLink = $links[0];

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
    private function resolveLinkKey(array $payload): ?string
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

        if (!preg_match('#/pr/([a-zA-Z0-9_-]{3,64})/?$#', $path, $matches)) {
            return null;
        }

        return $matches[1];
    }
}
