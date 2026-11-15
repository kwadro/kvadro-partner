<?php

namespace App\Controller;

use App\Entity\User;
use App\Form\RegistrationFormType;
use App\Repository\InviteLinkRepository;
use App\Service\PartnerOnboardingService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;

class RegistrationController extends AbstractController
{
    private const INVITE_SESSION_KEY = 'partner_invite_ref';

    public function __construct(
        private InviteLinkRepository $inviteLinkRepository,
        private PartnerOnboardingService $partnerOnboardingService,
    ) {
    }

    #[Route('/register', name: 'app_register')]
    public function register(
        Request $request,
        UserPasswordHasherInterface $passwordHasher,
        EntityManagerInterface $entityManager,
    ): Response {
        if ($this->getUser()) {
            return $this->redirectToRoute('app_cabinet');
        }

        $refFromQuery = trim((string) $request->query->get('ref', ''));
        if ('' !== $refFromQuery) {
            $request->getSession()->set(self::INVITE_SESSION_KEY, $refFromQuery);
        }

        $inviteKey = trim((string) $request->getSession()->get(self::INVITE_SESSION_KEY, ''));
        $inviteLink = '' !== $inviteKey
            ? $this->inviteLinkRepository->findOneByLinkKey($inviteKey)
            : null;

        $user = new User();
        $form = $this->createForm(RegistrationFormType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var string $plainPassword */
            $plainPassword = $form->get('plainPassword')->getData();

            $user->setPassword($passwordHasher->hashPassword($user, $plainPassword));
            $user->setBalance('0.00');

            $entityManager->persist($user);
            $this->partnerOnboardingService->onboard($user, $inviteLink);
            $entityManager->flush();

            $request->getSession()->remove(self::INVITE_SESSION_KEY);
            $this->addFlash('success', 'register.success');

            return $this->redirectToRoute('app_login');
        }

        return $this->render('registration/register.html.twig', [
            'registrationForm' => $form,
            'inviteLink' => $inviteLink,
        ]);
    }
}
