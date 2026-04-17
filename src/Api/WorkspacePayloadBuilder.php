<?php

namespace App\Api;

use App\Entity\Toast;
use App\Entity\ToastingSession;
use App\Entity\User;
use App\Entity\Workspace;
use App\Meeting\MeetingAgendaBuilder;
use App\Profile\AvatarUrlService;
use App\Profile\UserDateTimeFormatter;
use App\Repository\WorkspaceRepository;
use App\Workspace\InboundEmailAddressService;
use App\Workspace\WorkspaceWorkflowService;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class WorkspacePayloadBuilder
{
    public function __construct(
        private readonly MeetingAgendaBuilder $agendaBuilder,
        private readonly WorkspaceWorkflowService $workspaceWorkflow,
        private readonly WorkspaceRepository $workspaceRepository,
        private readonly AvatarUrlService $avatarUrl,
        private readonly UserDateTimeFormatter $userDateTimeFormatter,
        private readonly InboundEmailAddressService $inboundEmailAddress,
        private readonly UrlGeneratorInterface $urlGenerator,
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
                'email' => $currentUser->getPublicEmail(),
                'initials' => $currentUser->getInitials(),
                'gravatarUrl' => $this->avatarUrl->resolve($currentUser),
                'advancedAiModelEnabled' => $currentUser->isAdvancedAiModelEnabled(),
                'inboxEmailAddress' => $this->inboundEmailAddress->buildAddressForUser($currentUser),
            ],
            'workspace' => [
                'id' => $workspace->getId(),
                'name' => $workspace->getName(),
                'isDefault' => $workspace->isDefault(),
                'isInboxWorkspace' => $workspace->isInboxWorkspace(),
                'defaultDuePreset' => $workspace->getDefaultDuePreset(),
                'permalinkBackgroundUrl' => $this->resolvePermalinkBackgroundUrl($workspace),
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
                    'isInboxWorkspace' => $candidate->isInboxWorkspace(),
                ],
                array_values(array_filter(
                    $this->workspaceRepository->findForUser($currentUser),
                    static fn (Workspace $candidate): bool => $candidate->getId() !== $workspace->getId(),
                ))
            ),
            'memberships' => array_map(function ($membership): array {
                return [
                    'id' => $membership->getId(),
                    'isOwner' => $membership->isOwner(),
                    'user' => [
                        'id' => $membership->getUser()->getId(),
                        'displayName' => $membership->getUser()->getDisplayName(),
                        'email' => $membership->getUser()->getPublicEmail(),
                        'initials' => $membership->getUser()->getInitials(),
                        'gravatarUrl' => $this->avatarUrl->resolve($membership->getUser()),
                    ],
                ];
            }, $workspace->getMemberships()->toArray()),
            'participants' => array_map(
                fn (User $invitee): array => [
                    'id' => $invitee->getId(),
                    'displayName' => $invitee->getDisplayName(),
                    'email' => $invitee->getPublicEmail(),
                    'initials' => $invitee->getInitials(),
                    'gravatarUrl' => $this->avatarUrl->resolve($invitee),
                ],
                $invitees
            ),
            'toastingSessions' => array_map(
                fn (ToastingSession $session): array => $this->buildSessionPayload($session, $currentUser),
                $workspace->getToastingSessions()->toArray()
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
            'aiRefinementPending' => $item->isAiRefinementPending(),
            'status' => $item->getStatus(),
            'isBoosted' => $item->isBoosted(),
            'boostRank' => $item->getBoostRank(),
            'discussionNotes' => $item->getDiscussionNotes(),
            'previousItem' => $item->getPreviousItem() ? [
                'id' => $item->getPreviousItem()->getId(),
                'title' => $item->getPreviousItem()->getTitle(),
                'status' => $item->getPreviousItem()->getStatus(),
            ] : null,
            'followUpItems' => array_map(
                fn (Toast $followUp): array => [
                    'id' => $followUp->getId(),
                    'title' => $followUp->getTitle(),
                    'status' => $followUp->getStatus(),
                    'ownerId' => $followUp->getOwner()?->getId(),
                    'ownerName' => $followUp->getOwner()?->getDisplayName(),
                    'dueOn' => $followUp->getDueAt()?->format('Y-m-d'),
                    'dueOnDisplay' => $this->userDateTimeFormatter->formatDate($followUp->getDueAt(), $currentUser),
                ],
                $followUps
            ),
            'comments' => array_map(
                fn (\App\Entity\ToastComment $comment): array => [
                    'id' => $comment->getId(),
                    'content' => $comment->getContent(),
                    'createdAt' => $comment->getCreatedAt()->format(\DateTimeInterface::ATOM),
                    'createdAtDisplay' => $this->userDateTimeFormatter->formatDateTime($comment->getCreatedAt(), $currentUser),
                    'author' => [
                        'id' => $comment->getAuthor()->getId(),
                        'displayName' => $comment->getAuthor()->getDisplayName(),
                        'initials' => $comment->getAuthor()->getInitials(),
                        'gravatarUrl' => $this->avatarUrl->resolve($comment->getAuthor()),
                    ],
                ],
                $item->getComments()->toArray()
            ),
            'owner' => $item->getOwner() ? [
                'id' => $item->getOwner()->getId(),
                'displayName' => $item->getOwner()->getDisplayName(),
                'initials' => $item->getOwner()->getInitials(),
                'gravatarUrl' => $this->avatarUrl->resolve($item->getOwner()),
            ] : null,
            'dueOn' => $item->getDueAt()?->format('Y-m-d'),
            'dueOnDisplay' => $this->userDateTimeFormatter->formatDate($item->getDueAt(), $currentUser),
            'statusChangedAt' => $item->getStatusChangedAt()?->format('Y-m-d'),
            'statusChangedAtDisplay' => $this->userDateTimeFormatter->formatDate($item->getStatusChangedAt() ?? $item->getCreatedAt(), $currentUser),
            'author' => [
                'id' => $item->getAuthor()->getId(),
                'displayName' => $item->getAuthor()->getDisplayName(),
                'email' => $item->getAuthor()->getPublicEmail(),
                'initials' => $item->getAuthor()->getInitials(),
                'gravatarUrl' => $this->avatarUrl->resolve($item->getAuthor()),
            ],
            'voteCount' => $item->getVoteCount(),
            'currentUserHasVoted' => $hasVoted,
            'currentUserCanEdit' => $item->isNew() && ($workspace->isOwnedBy($currentUser) || $item->getAuthor()->getId() === $currentUser->getId()),
            'currentUserCanMarkReady' => !$workspace->isSoloWorkspace()
                && $item->isNew()
                && ($workspace->isOwnedBy($currentUser) || ($item->getOwner()?->getId() === $currentUser->getId())),
            'ownerName' => $item->getOwner()?->getDisplayName() ?? ($item->getOwner()?->getId() ? ($inviteeNames[$item->getOwner()->getId()] ?? null) : null),
        ];
    }

    public function buildSessionPayload(ToastingSession $session, User $currentUser): array
    {
        return [
            'id' => $session->getId(),
            'startedAt' => $session->getStartedAt()->format(\DateTimeInterface::ATOM),
            'startedAtDisplay' => $this->userDateTimeFormatter->formatDateTime($session->getStartedAt(), $currentUser),
            'endedAt' => $session->getEndedAt()?->format(\DateTimeInterface::ATOM),
            'endedAtDisplay' => $this->userDateTimeFormatter->formatDateTime($session->getEndedAt(), $currentUser),
            'isActive' => $session->isActive(),
            'startedBy' => [
                'id' => $session->getStartedBy()->getId(),
                'displayName' => $session->getStartedBy()->getDisplayName(),
            ],
            'endedBy' => $session->getEndedBy() ? [
                'id' => $session->getEndedBy()?->getId(),
                'displayName' => $session->getEndedBy()?->getDisplayName(),
            ] : null,
            'summary' => $session->getSummary(),
            'summaryGeneratedAt' => $session->getSummaryGeneratedAt()?->format(\DateTimeInterface::ATOM),
            'summaryGeneratedAtDisplay' => $this->userDateTimeFormatter->formatDateTime($session->getSummaryGeneratedAt(), $currentUser),
            'summaryUpdatedAt' => $session->getSummaryUpdatedAt()?->format(\DateTimeInterface::ATOM),
            'summaryUpdatedAtDisplay' => $this->userDateTimeFormatter->formatDateTime($session->getSummaryUpdatedAt(), $currentUser),
        ];
    }

    private function resolvePermalinkBackgroundUrl(Workspace $workspace): ?string
    {
        $background = $workspace->getPermalinkBackgroundUrl();

        if (null === $background || '' === $background) {
            return null;
        }

        if (str_starts_with($background, 'http://') || str_starts_with($background, 'https://')) {
            return $background;
        }

        return $this->urlGenerator->generate('api_workspace_background_get', ['id' => $workspace->getId()]);
    }
}
