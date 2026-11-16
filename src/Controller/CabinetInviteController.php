<?php

namespace App\Controller;

use App\Entity\InviteLink;
use App\Entity\User;
use App\Form\InviteLinkFormType;
use App\Repository\InviteLinkRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Translation\TranslatorInterface;

#[IsGranted('ROLE_USER')]
#[Route('/cabinet/invites')]
class CabinetInviteController extends AbstractController
{
    public function __construct(
        private InviteLinkRepository $inviteLinkRepository,
        private EntityManagerInterface $entityManager,
        private TranslatorInterface $translator,
    ) {
    }

    #[Route('', name: 'app_cabinet_invites')]
    public function index(Request $request): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        $inviteLink = new InviteLink();
        $form = $this->createForm(InviteLinkFormType::class, $inviteLink);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $linkKey = trim($inviteLink->getLinkKey());
            if (!$this->inviteLinkRepository->isKeyAvailable($linkKey)) {
                $form->get('linkKey')->addError(new FormError(
                    $this->translator->trans('cabinet.invites.form.key_taken')
                ));
            } else {
                $inviteLink
                    ->setUser($user)
                    ->setLinkKey($linkKey)
                    ->setTitle(trim($inviteLink->getTitle()));

                $this->entityManager->persist($inviteLink);
                $this->entityManager->flush();

                $this->addFlash('success', 'cabinet.invites.created');

                return $this->redirectToRoute('app_cabinet_invites');
            }
        }

        $links = $this->inviteLinkRepository->findByUserOrdered($user);
        $rows = [];
        foreach ($links as $link) {
            $rows[] = [
                'id' => $link->getId(),
                'title' => $link->getTitle(),
                'linkKey' => $link->getLinkKey(),
                'url' => $this->generateInviteUrl($link->getLinkKey()),
                'registrations' => $link->getRegistrations(),
                'createdAt' => $link->getCreatedAt()->format('Y-m-d'),
                'canEdit' => $link->canBeEdited(),
                'canDelete' => $link->canBeDeleted(),
            ];
        }

        return $this->render('cabinet/invites.html.twig', [
            'active_menu' => 'invites',
            'form' => $form,
            'links' => $rows,
            'previewBaseUrl' => $this->generateUrl('app_register', [], UrlGeneratorInterface::ABSOLUTE_URL),
        ]);
    }

    #[Route('/check-key', name: 'app_cabinet_invite_check_key', methods: ['POST'])]
    public function checkKey(Request $request): JsonResponse
    {
        $payload = json_decode($request->getContent(), true);
        $payload = \is_array($payload) ? $payload : [];
        $linkKey = trim((string) ($payload['linkKey'] ?? ''));
        $exceptId = (int) ($payload['exceptId'] ?? 0);

        if ('' === $linkKey) {
            return $this->json([
                'ok' => false,
                'available' => false,
                'message' => 'cabinet.invites.form.key_required',
            ], 400);
        }

        if (!$this->isValidLinkKey($linkKey)) {
            return $this->json([
                'ok' => false,
                'available' => false,
                'message' => 'cabinet.invites.form.key_invalid',
            ], 400);
        }

        $except = $exceptId > 0 ? $this->inviteLinkRepository->find($exceptId) : null;
        $available = $this->inviteLinkRepository->isKeyAvailable($linkKey, $except);

        return $this->json([
            'ok' => true,
            'available' => $available,
            'message' => $available
                ? 'cabinet.invites.form.key_available'
                : 'cabinet.invites.form.key_taken',
            'previewUrl' => $this->generateInviteUrl($linkKey),
        ]);
    }

    #[Route('/{id}/edit', name: 'app_cabinet_invite_edit', requirements: ['id' => '\d+'])]
    public function edit(int $id, Request $request): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $inviteLink = $this->inviteLinkRepository->find($id);

        if (!$inviteLink || $inviteLink->getUser()?->getId() !== $user->getId()) {
            throw $this->createNotFoundException('Invite link not found.');
        }

        if (!$inviteLink->canBeEdited()) {
            $this->addFlash('error', 'cabinet.invites.edit_forbidden');

            return $this->redirectToRoute('app_cabinet_invites');
        }

        $form = $this->createForm(InviteLinkFormType::class, $inviteLink);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $linkKey = trim($inviteLink->getLinkKey());
            if (!$this->inviteLinkRepository->isKeyAvailable($linkKey, $inviteLink)) {
                $form->get('linkKey')->addError(new FormError(
                    $this->translator->trans('cabinet.invites.form.key_taken')
                ));
            } else {
                $inviteLink
                    ->setLinkKey($linkKey)
                    ->setTitle(trim($inviteLink->getTitle()));

                $this->entityManager->flush();
                $this->addFlash('success', 'cabinet.invites.updated');

                return $this->redirectToRoute('app_cabinet_invites');
            }
        }

        return $this->render('cabinet/invite_edit.html.twig', [
            'active_menu' => 'invites',
            'form' => $form,
            'invite' => $inviteLink,
            'previewBaseUrl' => $this->generateUrl('app_register', [], UrlGeneratorInterface::ABSOLUTE_URL),
        ]);
    }

    #[Route('/{id}/delete', name: 'app_cabinet_invite_delete', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function delete(int $id, Request $request): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $link = $this->inviteLinkRepository->find($id);

        if (!$link || $link->getUser()?->getId() !== $user->getId()) {
            throw $this->createNotFoundException('Invite link not found.');
        }

        if (!$this->isCsrfTokenValid('delete_invite_'.$link->getId(), (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }

        if (!$link->canBeDeleted()) {
            $this->addFlash('error', 'cabinet.invites.delete_forbidden');

            return $this->redirectToRoute('app_cabinet_invites');
        }

        $this->entityManager->remove($link);
        $this->entityManager->flush();
        $this->addFlash('success', 'cabinet.invites.deleted');

        return $this->redirectToRoute('app_cabinet_invites');
    }

    private function generateInviteUrl(string $linkKey): string
    {
        return $this->generateUrl('app_register', [
            'ref' => $linkKey,
        ], UrlGeneratorInterface::ABSOLUTE_URL);
    }

    private function isValidLinkKey(string $linkKey): bool
    {
        return 1 === preg_match('/^[a-zA-Z0-9_-]{3,64}$/', $linkKey);
    }
}
