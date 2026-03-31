<?php

namespace App\Api;

use App\Entity\Meeting;
use App\Entity\Team;
use App\Entity\User;
use App\Repository\MeetingRepository;
use App\Repository\TeamRepository;

final class DashboardPayloadBuilder
{
    public function __construct(
        private readonly TeamRepository $teamRepository,
        private readonly MeetingRepository $meetingRepository,
    ) {
    }

    public function build(User $user): array
    {
        $adHocMeetings = array_map(fn (Meeting $meeting): array => $this->buildMeetingSummary($meeting), $this->meetingRepository->findAdHocForUser($user));

        return [
            'teams' => array_map(fn (Team $team): array => $this->buildTeamSummary($team), $this->teamRepository->findForUser($user)),
            'adHocMeetings' => array_values(array_filter($adHocMeetings, static fn (array $meeting): bool => 'closed' !== $meeting['status'])),
            'archivedAdHocMeetings' => array_values(array_filter($adHocMeetings, static fn (array $meeting): bool => 'closed' === $meeting['status'])),
        ];
    }

    private function buildTeamSummary(Team $team): array
    {
        $meetings = array_map(fn (Meeting $meeting): array => $this->buildMeetingSummary($meeting), $team->getMeetings()->toArray());

        return [
            'id' => $team->getId(),
            'name' => $team->getName(),
            'meetingCount' => $team->getMeetings()->count(),
            'itemCount' => $team->getItems()->count(),
            'meetings' => array_values(array_filter($meetings, static fn (array $meeting): bool => 'closed' !== $meeting['status'])),
            'archivedMeetings' => array_values(array_filter($meetings, static fn (array $meeting): bool => 'closed' === $meeting['status'])),
        ];
    }

    private function buildMeetingSummary(Meeting $meeting): array
    {
        return [
            'id' => $meeting->getId(),
            'title' => $meeting->getTitle(),
            'scheduledAt' => $meeting->getScheduledAt()->format(\DateTimeInterface::ATOM),
            'scheduledAtDisplay' => $meeting->getScheduledAt()->format('d/m/Y H:i'),
            'videoLink' => $meeting->getVideoLink(),
            'isRecurring' => $meeting->isRecurring(),
            'recurrence' => $meeting->getRecurrence(),
            'recurrenceDisplay' => $meeting->getRecurrenceDisplay(),
            'status' => $meeting->getStatus(),
            'teamId' => $meeting->getTeam()?->getId(),
        ];
    }
}
