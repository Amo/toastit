<?php

namespace App\Api;

use App\Entity\Toast;
use App\Entity\User;
use App\Entity\Workspace;
use App\Meeting\MeetingAgendaBuilder;
use App\Workspace\WorkspaceWorkflow;

final class WorkspacePayloadBuilder
{
    public function __construct(
        private readonly MeetingAgendaBuilder $agendaBuilder,
        private readonly WorkspaceWorkflow $workspaceWorkflow,
    ) {
    }

    public function build(Workspace $workspace, User $currentUser): array
    {
        $agenda = $this->agendaBuilder->build($workspace);
        $invitees = $this->workspaceWorkflow->getWorkspaceInvitees($workspace);

        return [
            'workspace' => [
                'id' => $workspace->getId(),
                'name' => $workspace->getName(),
                'organizerId' => $workspace->getOrganizer()->getId(),
                'meetingMode' => $workspace->getMeetingMode(),
                'meetingStartedAt' => $workspace->getMeetingStartedAt()?->format(\DateTimeInterface::ATOM),
                'meetingEndedAt' => $workspace->getMeetingEndedAt()?->format(\DateTimeInterface::ATOM),
                'currentUserIsOrganizer' => $workspace->getOrganizer()->getId() === $currentUser->getId(),
            ],
            'memberships' => array_map(static function ($membership): array {
                return [
                    'id' => $membership->getId(),
                    'user' => [
                        'id' => $membership->getUser()->getId(),
                        'displayName' => $membership->getUser()->getDisplayName(),
                        'email' => $membership->getUser()->getEmail(),
                        'initials' => $membership->getUser()->getInitials(),
                    ],
                ];
            }, $workspace->getMemberships()->toArray()),
            'participants' => array_map(
                static fn (User $invitee): array => [
                    'id' => $invitee->getId(),
                    'displayName' => $invitee->getDisplayName(),
                    'email' => $invitee->getEmail(),
                    'initials' => $invitee->getInitials(),
                ],
                $invitees
            ),
            'agendaItems' => array_map(
                fn (Toast $item): array => $this->buildItemPayload($item, $currentUser, $workspace),
                $agenda->activeItems
            ),
            'vetoedItems' => array_map(
                fn (Toast $item): array => $this->buildItemPayload($item, $currentUser, $workspace),
                $agenda->vetoedItems
            ),
            'resolvedItems' => array_map(
                fn (Toast $item): array => $this->buildItemPayload($item, $currentUser, $workspace),
                $agenda->resolvedItems
            ),
        ];
    }

    private function buildItemPayload(Toast $item, User $currentUser, Workspace $workspace): array
    {
        $hasVoted = false;

        foreach ($item->getVotes() as $vote) {
            if ($vote->getUser()->getId() === $currentUser->getId()) {
                $hasVoted = true;
                break;
            }
        }

        $inviteeNames = $this->workspaceWorkflow->getWorkspaceInviteeNamesById($workspace);
        $followUps = $item->getFollowUpChildren()->toArray();
        usort($followUps, static fn (Toast $left, Toast $right): int => $left->getCreatedAt() <=> $right->getCreatedAt());

        return [
            'id' => $item->getId(),
            'title' => $item->getTitle(),
            'description' => $item->getDescription(),
            'status' => $item->getStatus(),
            'isBoosted' => $item->isBoosted(),
            'boostRank' => $item->getBoostRank(),
            'discussionStatus' => $item->getDiscussionStatus(),
            'discussionNotes' => $item->getDiscussionNotes(),
            'followUpItems' => array_map(
                static fn (Toast $followUp): array => [
                    'id' => $followUp->getId(),
                    'title' => $followUp->getTitle(),
                    'ownerId' => $followUp->getOwner()?->getId(),
                    'ownerName' => $followUp->getOwner()?->getDisplayName(),
                    'dueOn' => $followUp->getDueAt()?->format('Y-m-d'),
                    'dueOnDisplay' => $followUp->getDueAt()?->format('d/m/Y'),
                ],
                $followUps
            ),
            'owner' => $item->getOwner() ? [
                'id' => $item->getOwner()->getId(),
                'displayName' => $item->getOwner()->getDisplayName(),
            ] : null,
            'dueOn' => $item->getDueAt()?->format('Y-m-d'),
            'dueOnDisplay' => $item->getDueAt()?->format('d/m/Y'),
            'author' => [
                'id' => $item->getAuthor()->getId(),
                'displayName' => $item->getAuthor()->getDisplayName(),
                'email' => $item->getAuthor()->getEmail(),
                'initials' => $item->getAuthor()->getInitials(),
            ],
            'voteCount' => $item->getVoteCount(),
            'currentUserHasVoted' => $hasVoted,
            'ownerName' => $item->getOwner()?->getDisplayName() ?? ($item->getOwner()?->getId() ? ($inviteeNames[$item->getOwner()->getId()] ?? null) : null),
        ];
    }
}
