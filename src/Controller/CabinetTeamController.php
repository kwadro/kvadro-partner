<?php

namespace App\Controller;

use App\Entity\User;
use App\Service\PartnerOnboardingService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
#[Route('/cabinet/team')]
class CabinetTeamController extends AbstractController
{
    public function __construct(
        private PartnerOnboardingService $partnerOnboardingService,
        private EntityManagerInterface $entityManager,
    ) {
    }

    #[Route('', name: 'app_cabinet_team')]
    public function index(): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $team = $this->partnerOnboardingService->ensureOwnedTeam($user);
        $this->entityManager->flush();

        $members = [];
        foreach ($team->getMembers() as $member) {
            $members[] = [
                'id' => $member->getId(),
                'fullName' => $member->getFullName(),
                'email' => $member->getEmail(),
                'phone' => $member->getPhone(),
                'joinedAt' => $member->getCreatedAt()->format('Y-m-d'),
                'inviteTitle' => $member->getInviteLink()?->getTitle(),
            ];
        }

        return $this->render('cabinet/team.html.twig', [
            'active_menu' => 'team',
            'team' => [
                'name' => $team->getName(),
                'createdAt' => $team->getCreatedAt()->format('Y-m-d'),
                'membersCount' => \count($members),
            ],
            'members' => $members,
            'invitedBy' => $user->getInvitedBy(),
        ]);
    }
}
