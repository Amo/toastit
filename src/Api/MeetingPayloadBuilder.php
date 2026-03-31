<?php

namespace App\Api;

use App\Entity\Meeting;
use App\Entity\ParkingLotItem;
use App\Entity\User;
use App\Meeting\MeetingAgendaBuilder;
use App\Workspace\MeetingWorkflow;

final class MeetingPayloadBuilder
{
    public function __construct(
        private readonly MeetingAgendaBuilder $meetingAgendaBuilder,
        private readonly MeetingWorkflow $meetingWorkflow,
    ) {
    }

    public function build(Meeting $meeting, User $currentUser): array
    {
        $agenda = $this->meetingAgendaBuilder->build($meeting);
        $invitees = $this->meetingWorkflow->getMeetingInvitees($meeting);
        $inviteesPayload = array_map(
            static fn (User $invitee): array => [
                'id' => $invitee->getId(),
                'displayName' => $invitee->getDisplayName(),
                'email' => $invitee->getEmail(),
                'initials' => $invitee->getInitials(),
            ],
            $invitees
        );

        return [
            'meeting' => [
                'id' => $meeting->getId(),
                'title' => $meeting->getTitle(),
                'status' => $meeting->getStatus(),
                'scheduledAt' => $meeting->getScheduledAt()->format(\DateTimeInterface::ATOM),
                'scheduledAtDisplay' => $meeting->getScheduledAt()->format('d/m/Y H:i'),
                'scheduledOnDisplay' => $meeting->getScheduledAt()->format('d/m/Y'),
                'startedAt' => $meeting->getStartedAt()?->format(\DateTimeInterface::ATOM),
                'closedAt' => $meeting->getClosedAt()?->format(\DateTimeInterface::ATOM),
                'isRecurring' => $meeting->isRecurring(),
                'recurrence' => $meeting->getRecurrence(),
                'recurrenceDisplay' => $meeting->getRecurrenceDisplay(),
                'videoLink' => $meeting->getVideoLink(),
                'teamId' => $meeting->getTeam()?->getId(),
                'teamName' => $meeting->getTeam()?->getName(),
                'currentUserIsOrganizer' => $meeting->getOrganizer()->getId() === $currentUser->getId(),
            ],
            'participants' => [
                'organizer' => [
                    'id' => $meeting->getOrganizer()->getId(),
                    'displayName' => $meeting->getOrganizer()->getDisplayName(),
                    'email' => $meeting->getOrganizer()->getEmail(),
                    'initials' => $meeting->getOrganizer()->getInitials(),
                ],
                'invitees' => $inviteesPayload,
            ],
            'agendaItems' => array_map(
                fn (ParkingLotItem $item): array => $this->buildItemPayload($item, $currentUser, $meeting),
                $agenda->activeItems
            ),
            'vetoedItems' => array_map(
                fn (ParkingLotItem $item): array => $this->buildItemPayload($item, $currentUser, $meeting),
                $agenda->vetoedItems
            ),
        ];
    }

    private function buildItemPayload(ParkingLotItem $item, User $currentUser, Meeting $meeting): array
    {
        $followUpItems = array_map(
            fn (array $followUpItem): array => [
                'title' => $followUpItem['title'],
                'ownerId' => $followUpItem['ownerId'],
                'ownerName' => $followUpItem['ownerId'] ? ($this->meetingWorkflow->getMeetingInviteeNamesById($meeting)[$followUpItem['ownerId']] ?? null) : null,
                'dueOn' => $followUpItem['dueOn'],
                'dueOnDisplay' => $followUpItem['dueOn'] ? (new \DateTimeImmutable($followUpItem['dueOn']))->format('d/m/Y') : null,
            ],
            $item->getFollowUpItems()
        );

        $hasVoted = false;
        foreach ($item->getVotes() as $vote) {
            if ($vote->getUser()->getId() === $currentUser->getId()) {
                $hasVoted = true;
                break;
            }
        }

        return [
            'id' => $item->getId(),
            'title' => $item->getTitle(),
            'description' => $item->getDescription(),
            'status' => $item->getStatus(),
            'isBoosted' => $item->isBoosted(),
            'boostRank' => $item->getBoostRank(),
            'discussionStatus' => $item->getDiscussionStatus(),
            'discussionNotes' => $item->getDiscussionNotes(),
            'followUpItems' => $followUpItems,
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
        ];
    }
}
