<?php

namespace App\Api;

use App\Entity\Meeting;
use App\Entity\Team;

final class TeamPayloadBuilder
{
    public function build(Team $team): array
    {
        $meetings = array_map(fn (Meeting $meeting): array => [
            'id' => $meeting->getId(),
            'title' => $meeting->getTitle(),
            'scheduledAt' => $meeting->getScheduledAt()->format(\DateTimeInterface::ATOM),
            'scheduledAtDisplay' => $meeting->getScheduledAt()->format('d/m/Y H:i'),
            'videoLink' => $meeting->getVideoLink(),
            'isRecurring' => $meeting->isRecurring(),
            'recurrence' => $meeting->getRecurrence(),
            'recurrenceDisplay' => $meeting->getRecurrenceDisplay(),
            'status' => $meeting->getStatus(),
        ], $team->getMeetings()->toArray());

        return [
            'team' => [
                'id' => $team->getId(),
                'name' => $team->getName(),
                'organizerId' => $team->getOrganizer()->getId(),
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
            }, $team->getMemberships()->toArray()),
            'meetings' => array_values(array_filter($meetings, static fn (array $meeting): bool => 'closed' !== $meeting['status'])),
            'archivedMeetings' => array_values(array_filter($meetings, static fn (array $meeting): bool => 'closed' === $meeting['status'])),
        ];
    }
}
