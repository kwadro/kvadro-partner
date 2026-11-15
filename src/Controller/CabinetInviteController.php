<?php

namespace App\Controller;

use App\Entity\InviteLink;
use App\Entity\User;
use App\Form\InviteLinkFormType;
use App\Repository\InviteLinkRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
#[Route('/cabinet/invites')]
class CabinetInviteController extends AbstractController
{
    public function __construct(
        private InviteLinkRepository $inviteLinkRepository,
        private EntityManagerInterface $entityManager,
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
                $this->addFlash('error', 'cabinet.invites.form.key_taken');
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
            ];
        }

        return $this->render('cabinet/invites.html.twig', [
            'active_menu' => 'invites',
            'form' => $form,
            'links' => $rows,
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

        if ($link->getRegistrations() > 0) {
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
}
