<?php

namespace App\Api;

use App\Entity\Toast;
use App\Entity\User;
use App\Entity\Workspace;
use App\Meeting\MeetingAgendaBuilder;
use App\Repository\WorkspaceRepository;
use App\Workspace\WorkspaceWorkflowService;

final class WorkspacePayloadBuilder
{
    public function __construct(
        private readonly MeetingAgendaBuilder $agendaBuilder,
        private readonly WorkspaceWorkflowService $workspaceWorkflow,
        private readonly WorkspaceRepository $workspaceRepository,
    ) {
    }

    public function build(Workspace $workspace, User $currentUser): array
    {
        $agenda = $this->agendaBuilder->build($workspace);
        $invitees = $this->workspaceWorkflow->getWorkspaceInvitees($workspace);

        return [
            'currentUser' => [
                'id' => $currentUser->getId(),
                'displayName' => $currentUser->getDisplayName(),
                'email' => $currentUser->getEmail(),
                'initials' => $currentUser->getInitials(),
                'gravatarUrl' => $currentUser->getGravatarUrl(),
            ],
            'workspace' => [
                'id' => $workspace->getId(),
                'name' => $workspace->getName(),
                'isDefault' => $workspace->isDefault(),
                'defaultDuePreset' => $workspace->getDefaultDuePreset(),
                'permalinkBackgroundUrl' => $workspace->getPermalinkBackgroundUrl(),
                'isSoloWorkspace' => $workspace->isSoloWorkspace(),
                'meetingMode' => $workspace->getMeetingMode(),
                'meetingStartedAt' => $workspace->getMeetingStartedAt()?->format(\DateTimeInterface::ATOM),
                'meetingEndedAt' => $workspace->getMeetingEndedAt()?->format(\DateTimeInterface::ATOM),
                'currentUserIsOwner' => $workspace->isOwnedBy($currentUser),
                'ownerCount' => $workspace->getOwnerCount(),
            ],
            'otherWorkspaces' => array_map(
                static fn (Workspace $candidate): array => [
                    'id' => $candidate->getId(),
                    'name' => $candidate->getName(),
                    'isSoloWorkspace' => $candidate->isSoloWorkspace(),
                ],
                array_values(array_filter(
                    $this->workspaceRepository->findForUser($currentUser),
                    static fn (Workspace $candidate): bool => $candidate->getId() !== $workspace->getId(),
                ))
            ),
            'memberships' => array_map(static function ($membership): array {
                return [
                    'id' => $membership->getId(),
                    'isOwner' => $membership->isOwner(),
                    'user' => [
                        'id' => $membership->getUser()->getId(),
                        'displayName' => $membership->getUser()->getDisplayName(),
                        'email' => $membership->getUser()->getEmail(),
                        'initials' => $membership->getUser()->getInitials(),
                        'gravatarUrl' => $membership->getUser()->getGravatarUrl(),
                    ],
                ];
            }, $workspace->getMemberships()->toArray()),
            'participants' => array_map(
                static fn (User $invitee): array => [
                    'id' => $invitee->getId(),
                    'displayName' => $invitee->getDisplayName(),
                    'email' => $invitee->getEmail(),
                    'initials' => $invitee->getInitials(),
                    'gravatarUrl' => $invitee->getGravatarUrl(),
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
            'previousItem' => $item->getPreviousItem() ? [
                'id' => $item->getPreviousItem()->getId(),
                'title' => $item->getPreviousItem()->getTitle(),
                'status' => $item->getPreviousItem()->getStatus(),
                'discussionStatus' => $item->getPreviousItem()->getDiscussionStatus(),
            ] : null,
            'followUpItems' => array_map(
                static fn (Toast $followUp): array => [
                    'id' => $followUp->getId(),
                    'title' => $followUp->getTitle(),
                    'status' => $followUp->getStatus(),
                    'discussionStatus' => $followUp->getDiscussionStatus(),
                    'ownerId' => $followUp->getOwner()?->getId(),
                    'ownerName' => $followUp->getOwner()?->getDisplayName(),
                    'dueOn' => $followUp->getDueAt()?->format('Y-m-d'),
                    'dueOnDisplay' => $followUp->getDueAt()?->format('d/m/Y'),
                ],
                $followUps
            ),
            'comments' => array_map(
                static fn (\App\Entity\ToastComment $comment): array => [
                    'id' => $comment->getId(),
                    'content' => $comment->getContent(),
                    'createdAt' => $comment->getCreatedAt()->format(\DateTimeInterface::ATOM),
                    'createdAtDisplay' => $comment->getCreatedAt()->format('d/m/Y H:i'),
                    'author' => [
                        'id' => $comment->getAuthor()->getId(),
                        'displayName' => $comment->getAuthor()->getDisplayName(),
                        'initials' => $comment->getAuthor()->getInitials(),
                        'gravatarUrl' => $comment->getAuthor()->getGravatarUrl(),
                    ],
                ],
                $item->getComments()->toArray()
            ),
            'owner' => $item->getOwner() ? [
                'id' => $item->getOwner()->getId(),
                'displayName' => $item->getOwner()->getDisplayName(),
            ] : null,
            'dueOn' => $item->getDueAt()?->format('Y-m-d'),
            'dueOnDisplay' => $item->getDueAt()?->format('d/m/Y'),
            'statusChangedAt' => $item->getStatusChangedAt()?->format('Y-m-d'),
            'statusChangedAtDisplay' => ($item->getStatusChangedAt() ?? $item->getCreatedAt())->format('d/m/Y'),
            'author' => [
                'id' => $item->getAuthor()->getId(),
                'displayName' => $item->getAuthor()->getDisplayName(),
                'email' => $item->getAuthor()->getEmail(),
                'initials' => $item->getAuthor()->getInitials(),
                'gravatarUrl' => $item->getAuthor()->getGravatarUrl(),
            ],
            'voteCount' => $item->getVoteCount(),
            'currentUserHasVoted' => $hasVoted,
            'ownerName' => $item->getOwner()?->getDisplayName() ?? ($item->getOwner()?->getId() ? ($inviteeNames[$item->getOwner()->getId()] ?? null) : null),
        ];
    }
}
