<?php

namespace App\Controller;

use App\Entity\AdvertisingLink;
use App\Entity\User;
use App\Repository\AdvertisingLinkRepository;
use App\Repository\PartnerRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
#[Route('/cabinet')]
class CabinetController extends AbstractController
{
    public function __construct(
        private PartnerRepository $partnerRepository,
        private AdvertisingLinkRepository $advertisingLinkRepository,
        private EntityManagerInterface $entityManager,
    ) {
    }

    #[Route('', name: 'app_cabinet')]
    public function account(): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        return $this->render('cabinet/account.html.twig', [
            'active_menu' => 'account',
            'user' => $user,
        ]);
    }

    #[Route('/links', name: 'app_cabinet_links')]
    public function links(): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        return $this->render('cabinet/links.html.twig', [
            'active_menu' => 'links',
            'links' => $this->advertisingLinkRepository->findByUserOrdered($user),
            'partners' => $this->partnerRepository->findBy([], ['name' => 'ASC']),
        ]);
    }

    #[Route('/links/check-key', name: 'app_cabinet_link_check_key', methods: ['POST'])]
    public function checkKey(Request $request): JsonResponse
    {
        $payload = $this->decodeJson($request);
        $partnerId = (int) ($payload['partnerId'] ?? 0);
        $linkKey = trim((string) ($payload['linkKey'] ?? ''));

        if ($partnerId < 1 || '' === $linkKey) {
            return $this->json([
                'ok' => false,
                'available' => false,
                'message' => 'cabinet.links.popup.key_required',
            ], 400);
        }

        if (!$this->isValidLinkKey($linkKey)) {
            return $this->json([
                'ok' => false,
                'available' => false,
                'message' => 'cabinet.links.popup.key_invalid',
            ], 400);
        }

        $partner = $this->partnerRepository->find($partnerId);
        if (!$partner) {
            return $this->json([
                'ok' => false,
                'available' => false,
                'message' => 'cabinet.links.popup.partner_required',
            ], 404);
        }

        $available = $this->advertisingLinkRepository->isKeyAvailable($partner, $linkKey);

        return $this->json([
            'ok' => true,
            'available' => $available,
            'message' => $available
                ? 'cabinet.links.popup.key_available'
                : 'cabinet.links.popup.key_taken',
            'previewUrl' => $partner->buildReferralUrl($linkKey),
        ]);
    }

    #[Route('/links/create', name: 'app_cabinet_link_create', methods: ['POST'])]
    public function createLink(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        $payload = $this->decodeJson($request);
        $partnerId = (int) ($payload['partnerId'] ?? 0);
        $linkKey = trim((string) ($payload['linkKey'] ?? ''));

        if ($partnerId < 1) {
            return $this->json([
                'ok' => false,
                'message' => 'cabinet.links.popup.partner_required',
            ], 400);
        }

        if ('' === $linkKey || !$this->isValidLinkKey($linkKey)) {
            return $this->json([
                'ok' => false,
                'message' => 'cabinet.links.popup.key_invalid',
            ], 400);
        }

        $partner = $this->partnerRepository->find($partnerId);
        if (!$partner) {
            return $this->json([
                'ok' => false,
                'message' => 'cabinet.links.popup.partner_required',
            ], 404);
        }

        if (!$this->advertisingLinkRepository->isKeyAvailable($partner, $linkKey)) {
            return $this->json([
                'ok' => false,
                'message' => 'cabinet.links.popup.key_taken',
            ], 409);
        }

        $link = (new AdvertisingLink())
            ->setUser($user)
            ->setPartner($partner)
            ->setLinkKey($linkKey);

        $this->entityManager->persist($link);
        $this->entityManager->flush();

        return $this->json([
            'ok' => true,
            'message' => 'cabinet.links.popup.saved',
            'link' => [
                'id' => $link->getId(),
                'url' => $link->getUrl(),
                'linkKey' => $link->getLinkKey(),
                'partnerName' => $partner->getName(),
                'clicks' => $link->getClicks(),
                'orders' => $link->getOrders(),
                'reward' => $link->getReward(),
            ],
        ]);
    }

    #[Route('/links/{id}/delete', name: 'app_cabinet_link_delete', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function deleteLink(int $id): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        $link = $this->advertisingLinkRepository->find($id);

        if (!$link || $link->getUser()?->getId() !== $user->getId()) {
            return $this->json([
                'ok' => false,
                'message' => 'cabinet.links.delete.not_found',
            ], 404);
        }

        if (!$link->canBeDeleted()) {
            return $this->json([
                'ok' => false,
                'message' => 'cabinet.links.delete.forbidden',
            ], 409);
        }

        $this->entityManager->remove($link);
        $this->entityManager->flush();

        return $this->json([
            'ok' => true,
            'message' => 'cabinet.links.delete.success',
        ]);
    }

    #[Route('/links/{id}', name: 'app_cabinet_link_show', requirements: ['id' => '\d+'])]
    public function linkShow(int $id): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $link = $this->advertisingLinkRepository->find($id);

        if (!$link || $link->getUser()?->getId() !== $user->getId()) {
            throw $this->createNotFoundException('Advertising link not found.');
        }

        return $this->render('cabinet/link_show.html.twig', [
            'active_menu' => 'links',
            'link' => [
                'id' => $link->getId(),
                'name' => $link->getLinkKey(),
                'url' => $link->getUrl(),
                'clicks' => $link->getClicks(),
                'unique_visitors' => 0,
                'orders' => $link->getOrders(),
                'reward' => $link->getReward(),
                'created_at' => $link->getCreatedAt()->format('Y-m-d'),
                'activity' => [],
                'purchases' => [],
            ],
        ]);
    }

    #[Route('/finance', name: 'app_cabinet_finance')]
    public function finance(): Response
    {
        $operations = [
            [
                'date' => '2026-11-12',
                'type' => 'accrual',
                'description_key' => 'cabinet.finance.reward_for_order',
                'description_params' => ['%order%' => 'ORD-1042'],
                'amount' => '+49.00',
                'balance_after' => '1250.00',
            ],
            [
                'date' => '2026-11-12',
                'type' => 'accrual',
                'description_key' => 'cabinet.finance.reward_for_order',
                'description_params' => ['%order%' => 'ORD-1038'],
                'amount' => '+49.00',
                'balance_after' => '1201.00',
            ],
            [
                'date' => '2026-11-08',
                'type' => 'withdrawal',
                'description_key' => 'cabinet.finance.payout_to_card',
                'description_params' => ['%card%' => '4412'],
                'amount' => '-500.00',
                'balance_after' => '1152.00',
            ],
            [
                'date' => '2026-11-05',
                'type' => 'accrual',
                'description_key' => 'cabinet.finance.reward_for_order',
                'description_params' => ['%order%' => 'ORD-1011'],
                'amount' => '+45.00',
                'balance_after' => '1652.00',
            ],
            [
                'date' => '2026-10-28',
                'type' => 'accrual',
                'description_key' => 'cabinet.finance.reward_for_order',
                'description_params' => ['%order%' => 'ORD-0955'],
                'amount' => '+28.00',
                'balance_after' => '1607.00',
            ],
            [
                'date' => '2026-10-15',
                'type' => 'withdrawal',
                'description_key' => 'cabinet.finance.payout_to_card',
                'description_params' => ['%card%' => '4412'],
                'amount' => '-300.00',
                'balance_after' => '1579.00',
            ],
        ];

        return $this->render('cabinet/finance.html.twig', [
            'active_menu' => 'finance',
            'operations' => $operations,
        ]);
    }

    /**
     * @return array
     */
    private function decodeJson(Request $request): array
    {
        $data = json_decode($request->getContent(), true);

        return \is_array($data) ? $data : [];
    }

    private function isValidLinkKey(string $linkKey): bool
    {
        return 1 === preg_match('/^[a-zA-Z0-9_-]{3,64}$/', $linkKey);
    }
}
