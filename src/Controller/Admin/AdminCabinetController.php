<?php

namespace App\Controller\Admin;

use App\Entity\Partner;
use App\Entity\PartnerPage;
use App\Repository\AdvertisingLinkRepository;
use App\Repository\PartnerPageRepository;
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
        private PartnerPageRepository $partnerPageRepository,
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
            ->setEmail($this->nullableTrim($payload['email'] ?? null))
            ->setPhone($this->nullableTrim($payload['phone'] ?? null));

        $this->applyRewardPercents($partner, $payload);

        $syncError = $this->syncPartnerPages($partner, $payload['pages'] ?? []);
        if (null !== $syncError) {
            return $syncError;
        }

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
            ->setEmail($this->nullableTrim($payload['email'] ?? null))
            ->setPhone($this->nullableTrim($payload['phone'] ?? null));

        $this->applyRewardPercents($partner, $payload);

        $syncError = $this->syncPartnerPages($partner, $payload['pages'] ?? []);
        if (null !== $syncError) {
            return $syncError;
        }

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
        $email = trim((string) ($payload['email'] ?? ''));
        $pages = $payload['pages'] ?? null;

        if ('' === $name) {
            return $this->json([
                'ok' => false,
                'message' => 'admin.cabinet.partners.form.name_required',
            ], 400);
        }

        if ('' !== $email && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return $this->json([
                'ok' => false,
                'message' => 'admin.cabinet.partners.form.email_invalid',
            ], 400);
        }

        foreach (['ownPercent', 'level1Percent', 'level2Percent'] as $field) {
            if (!array_key_exists($field, $payload)) {
                continue;
            }
            if (!$this->isValidPercent($payload[$field])) {
                return $this->json([
                    'ok' => false,
                    'message' => 'admin.cabinet.partners.form.percent_invalid',
                ], 400);
            }
        }

        if (!\is_array($pages) || 0 === \count($pages)) {
            return $this->json([
                'ok' => false,
                'message' => 'admin.cabinet.partners.form.pages_required',
            ], 400);
        }

        return null;
    }

    /**
     * @param array $payload
     */
    private function applyRewardPercents(Partner $partner, array $payload): void
    {
        if (array_key_exists('ownPercent', $payload) && $this->isValidPercent($payload['ownPercent'])) {
            $partner->setOwnPercent($payload['ownPercent']);
        }
        if (array_key_exists('level1Percent', $payload) && $this->isValidPercent($payload['level1Percent'])) {
            $partner->setLevel1Percent($payload['level1Percent']);
        }
        if (array_key_exists('level2Percent', $payload) && $this->isValidPercent($payload['level2Percent'])) {
            $partner->setLevel2Percent($payload['level2Percent']);
        }
    }

    private function isValidPercent(mixed $value): bool
    {
        if (!is_numeric($value)) {
            return false;
        }

        $percent = (float) $value;

        return $percent >= 0 && $percent <= 100;
    }

    /**
     * @param array $pagesPayload
     */
    private function syncPartnerPages(Partner $partner, array $pagesPayload): ?JsonResponse
    {
        $normalized = [];
        $seenCodes = [];
        $seenUrls = [];

        foreach ($pagesPayload as $item) {
            if (!\is_array($item)) {
                continue;
            }

            $id = isset($item['id']) ? (int) $item['id'] : null;
            $url = trim((string) ($item['url'] ?? ''));
            $code = strtolower(trim((string) ($item['code'] ?? '')));

            if ('' === $url || !filter_var($url, FILTER_VALIDATE_URL)) {
                return $this->json([
                    'ok' => false,
                    'message' => 'admin.cabinet.partners.form.page_url_required',
                ], 400);
            }

            if (1 !== preg_match('/^[a-z0-9]{2}$/', $code)) {
                return $this->json([
                    'ok' => false,
                    'message' => 'admin.cabinet.partners.form.page_code_invalid',
                ], 400);
            }

            if (isset($seenCodes[$code])) {
                return $this->json([
                    'ok' => false,
                    'message' => 'admin.cabinet.partners.form.page_code_duplicate',
                ], 400);
            }

            $urlNormalized = rtrim($url, '/');
            if (isset($seenUrls[$urlNormalized])) {
                return $this->json([
                    'ok' => false,
                    'message' => 'admin.cabinet.partners.form.page_url_duplicate',
                ], 400);
            }

            $existingByCode = $this->partnerPageRepository->findOneByCode($code);
            if (null !== $existingByCode && $existingByCode->getPartner()?->getId() !== $partner->getId()) {
                return $this->json([
                    'ok' => false,
                    'message' => 'admin.cabinet.partners.form.page_code_taken',
                ], 409);
            }

            if (null !== $existingByCode && null !== $id && $existingByCode->getId() !== $id) {
                return $this->json([
                    'ok' => false,
                    'message' => 'admin.cabinet.partners.form.page_code_taken',
                ], 409);
            }

            if (null !== $existingByCode && null === $id && null !== $partner->getId()
                && $existingByCode->getPartner()?->getId() === $partner->getId()
                && $existingByCode->getId() !== null) {
                // Reuse existing page id when payload omitted it but code matches this partner.
                $id = $existingByCode->getId();
            }

            $seenCodes[$code] = true;
            $seenUrls[$urlNormalized] = true;
            $normalized[] = [
                'id' => $id && $id > 0 ? $id : null,
                'url' => $urlNormalized,
                'code' => $code,
            ];
        }

        if (0 === \count($normalized)) {
            return $this->json([
                'ok' => false,
                'message' => 'admin.cabinet.partners.form.pages_required',
            ], 400);
        }

        $keepIds = [];
        foreach ($normalized as $item) {
            $page = null;
            if (null !== $item['id']) {
                foreach ($partner->getPages() as $existingPage) {
                    if ($existingPage->getId() === $item['id']) {
                        $page = $existingPage;
                        break;
                    }
                }
                if (null === $page) {
                    return $this->json([
                        'ok' => false,
                        'message' => 'admin.cabinet.partners.form.page_not_found',
                    ], 404);
                }
            } else {
                $page = new PartnerPage();
                $partner->addPage($page);
            }

            $page->setUrl($item['url'])->setCode($item['code']);
            if (null !== $page->getId()) {
                $keepIds[] = $page->getId();
            }
        }

        foreach ($partner->getPages()->toArray() as $existingPage) {
            $pageId = $existingPage->getId();
            if (null === $pageId || \in_array($pageId, $keepIds, true)) {
                continue;
            }

            if ($this->advertisingLinkRepository->countByPartnerPage($existingPage) > 0) {
                return $this->json([
                    'ok' => false,
                    'message' => 'admin.cabinet.partners.form.page_delete_forbidden',
                ], 409);
            }

            $partner->removePage($existingPage);
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
        $pages = [];
        foreach ($partner->getPages() as $page) {
            $pages[] = [
                'id' => $page->getId(),
                'url' => $page->getUrl(),
                'code' => $page->getCode(),
            ];
        }

        return [
            'id' => $partner->getId(),
            'name' => $partner->getName(),
            'email' => $partner->getEmail(),
            'phone' => $partner->getPhone(),
            'ownPercent' => $partner->getOwnPercent(),
            'level1Percent' => $partner->getLevel1Percent(),
            'level2Percent' => $partner->getLevel2Percent(),
            'createdAt' => $partner->getCreatedAt()->format('Y-m-d'),
            'pages' => $pages,
            'pagesCount' => \count($pages),
            'pagesLabel' => $this->formatPagesLabel($pages),
        ];
    }

    /**
     * @param array $pages
     */
    private function formatPagesLabel(array $pages): string
    {
        if (0 === \count($pages)) {
            return '—';
        }

        $parts = [];
        foreach ($pages as $page) {
            $parts[] = sprintf('[%s] %s', $page['code'], $page['url']);
        }

        return implode(', ', $parts);
    }
}
