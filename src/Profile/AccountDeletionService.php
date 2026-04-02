<?php

namespace App\Profile;

use App\Entity\ApiRefreshToken;
use App\Entity\LoginChallenge;
use App\Entity\User;
use App\Entity\Workspace;
use App\Entity\WorkspaceMember;
use Doctrine\ORM\EntityManagerInterface;

final class AccountDeletionService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function delete(User $user): void
    {
        /** @var list<WorkspaceMember> $memberships */
        $memberships = $this->entityManager->getRepository(WorkspaceMember::class)->findBy(['user' => $user], ['id' => 'ASC']);

        foreach ($memberships as $membership) {
            $workspace = $membership->getWorkspace();
            $remainingMemberships = array_values(array_filter(
                $workspace->getMemberships()->toArray(),
                static fn (WorkspaceMember $candidate): bool => $candidate->getId() !== $membership->getId()
            ));

            usort($remainingMemberships, static fn (WorkspaceMember $left, WorkspaceMember $right): int => $left->getId() <=> $right->getId());

            if ([] === $remainingMemberships) {
                $this->entityManager->remove($workspace);
                continue;
            }

            $firstRemainingMembership = $remainingMemberships[0];

            if ($workspace->getOrganizer()->getId() === $user->getId()) {
                $workspace->setOrganizer($firstRemainingMembership->getUser());
            }

            if ($membership->isOwner()) {
                $hasOtherOwner = false;

                foreach ($remainingMemberships as $candidate) {
                    if ($candidate->isOwner()) {
                        $hasOtherOwner = true;
                        break;
                    }
                }

                if (!$hasOtherOwner) {
                    $firstRemainingMembership->setIsOwner(true);
                }
            }

            $workspace->removeMembership($membership);
            $this->entityManager->remove($membership);
        }

        $this->entityManager->createQuery('DELETE FROM App\Entity\ApiRefreshToken token WHERE token.user = :user')
            ->setParameter('user', $user)
            ->execute();

        $this->entityManager->createQuery('DELETE FROM App\Entity\LoginChallenge challenge WHERE challenge.user = :user')
            ->setParameter('user', $user)
            ->execute();

        $user->anonymize();

        $this->entityManager->flush();
    }
}
