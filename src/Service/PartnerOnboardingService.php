<?php

namespace App\Service;

use App\Entity\InviteLink;
use App\Entity\Team;
use App\Entity\User;
use App\Repository\TeamRepository;
use Doctrine\ORM\EntityManagerInterface;

class PartnerOnboardingService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private TeamRepository $teamRepository,
    ) {
    }

    /**
     * Ensures the user has an owned team and, when invited, joins the inviter's team.
     */
    public function onboard(User $user, ?InviteLink $inviteLink = null): void
    {
        $this->ensureOwnedTeam($user);

        if (null === $inviteLink || null === $inviteLink->getUser()) {
            return;
        }

        $inviter = $inviteLink->getUser();
        if ($inviter->getId() === $user->getId()) {
            return;
        }

        $inviterTeam = $this->ensureOwnedTeam($inviter);

        $user->setInvitedBy($inviter);
        $user->setInviteLink($inviteLink);
        $inviterTeam->addMember($user);
        $inviteLink->incrementRegistrations();
    }

    public function ensureOwnedTeam(User $user): Team
    {
        $team = $user->getOwnedTeam();
        if (null !== $team) {
            return $team;
        }

        $existing = $this->teamRepository->findOneByOwner($user);
        if (null !== $existing) {
            $user->setOwnedTeam($existing);

            return $existing;
        }

        $team = (new Team())
            ->setOwner($user)
            ->setName($this->defaultTeamName($user));

        $user->setOwnedTeam($team);
        $this->entityManager->persist($team);

        return $team;
    }

    private function defaultTeamName(User $user): string
    {
        $name = trim((string) $user->getFullName());
        if ('' === $name) {
            $name = (string) $user->getEmail();
        }

        return sprintf('Team of %s', $name);
    }
}
