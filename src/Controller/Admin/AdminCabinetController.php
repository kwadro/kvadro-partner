<?php

namespace App\Controller\Admin;

use App\Entity\Partner;
use App\Repository\AdvertisingLinkRepository;
use App\Repository\PartnerRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
#[Route('/admin_9fg!lp')]
class AdminCabinetController extends AbstractController
{
    public function __construct(
        private PartnerRepository $partnerRepository,
        private AdvertisingLinkRepository $advertisingLinkRepository,
        private EntityManagerInterface $entityManager,
    ) {
    }

    #[Route('', name: 'app_admin')]
    public function index(): Response
    {
        return $this->render('admin/cabinet.html.twig', [
            'active_menu' => 'dashboard',
        ]);
    }

    #[Route('/partners', name: 'app_admin_partners')]
    public function partners(): Response
    {
        return $this->render('admin/partners.html.twig', [
            'active_menu' => 'partners',
            'partners' => $this->partnerRepository->findBy([], ['id' => 'DESC']),
        ]);
    }

    #[Route('/partners/create', name: 'app_admin_partners_create', methods: ['POST'])]
    public function createPartner(Request $request): JsonResponse
    {
        $payload = $this->decodeJson($request);
        $validationError = $this->validatePartnerPayload($payload);
        if (null !== $validationError) {
            return $validationError;
        }

        $partner = (new Partner())
            ->setName(trim((string) $payload['name']))
            ->setUrl(trim((string) $payload['url']))
            ->setEmail($this->nullableTrim($payload['email'] ?? null))
            ->setPhone($this->nullableTrim($payload['phone'] ?? null));

        $this->entityManager->persist($partner);
        $this->entityManager->flush();

        return $this->json([
            'ok' => true,
            'message' => 'admin.cabinet.partners.created',
            'partner' => $this->serializePartner($partner),
        ]);
    }

    #[Route('/partners/{id}/update', name: 'app_admin_partners_update', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function updatePartner(int $id, Request $request): JsonResponse
    {
        $partner = $this->partnerRepository->find($id);
        if (!$partner) {
            return $this->json([
                'ok' => false,
                'message' => 'admin.cabinet.partners.not_found',
            ], 404);
        }

        $payload = $this->decodeJson($request);
        $validationError = $this->validatePartnerPayload($payload);
        if (null !== $validationError) {
            return $validationError;
        }

        $partner
            ->setName(trim((string) $payload['name']))
            ->setUrl(trim((string) $payload['url']))
            ->setEmail($this->nullableTrim($payload['email'] ?? null))
            ->setPhone($this->nullableTrim($payload['phone'] ?? null));

        $this->entityManager->flush();

        return $this->json([
            'ok' => true,
            'message' => 'admin.cabinet.partners.updated',
            'partner' => $this->serializePartner($partner),
        ]);
    }

    #[Route('/partners/{id}/delete', name: 'app_admin_partners_delete', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function deletePartner(int $id): JsonResponse
    {
        $partner = $this->partnerRepository->find($id);
        if (!$partner) {
            return $this->json([
                'ok' => false,
                'message' => 'admin.cabinet.partners.not_found',
            ], 404);
        }

        if ($this->advertisingLinkRepository->countByPartner($partner) > 0) {
            return $this->json([
                'ok' => false,
                'message' => 'admin.cabinet.partners.delete_forbidden',
            ], 409);
        }

        $this->entityManager->remove($partner);
        $this->entityManager->flush();

        return $this->json([
            'ok' => true,
            'message' => 'admin.cabinet.partners.deleted',
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

    /**
     * @param array $payload
     */
    private function validatePartnerPayload(array $payload): ?JsonResponse
    {
        $name = trim((string) ($payload['name'] ?? ''));
        $url = trim((string) ($payload['url'] ?? ''));
        $email = trim((string) ($payload['email'] ?? ''));

        if ('' === $name) {
            return $this->json([
                'ok' => false,
                'message' => 'admin.cabinet.partners.form.name_required',
            ], 400);
        }

        if ('' === $url || !filter_var($url, FILTER_VALIDATE_URL)) {
            return $this->json([
                'ok' => false,
                'message' => 'admin.cabinet.partners.form.url_required',
            ], 400);
        }

        if ('' !== $email && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return $this->json([
                'ok' => false,
                'message' => 'admin.cabinet.partners.form.email_invalid',
            ], 400);
        }

        return null;
    }

    private function nullableTrim(mixed $value): ?string
    {
        $trimmed = trim((string) $value);

        return '' !== $trimmed ? $trimmed : null;
    }

    /**
     * @return array
     */
    private function serializePartner(Partner $partner): array
    {
        return [
            'id' => $partner->getId(),
            'name' => $partner->getName(),
            'url' => $partner->getUrl(),
            'email' => $partner->getEmail(),
            'phone' => $partner->getPhone(),
            'createdAt' => $partner->getCreatedAt()->format('Y-m-d'),
        ];
    }
}
