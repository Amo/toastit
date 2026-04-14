<?php

namespace App\Api;

use App\Entity\User;
use App\Entity\Workspace;
use App\Profile\AvatarUrlService;
use App\Profile\UserDateTimeFormatter;
use App\Repository\WorkspaceRepository;
use App\Workspace\InboundEmailAddressService;

final class ProfilePayloadBuilder
{
    public function __construct(
        private readonly AvatarUrlService $avatarUrl,
        private readonly UserDateTimeFormatter $userDateTimeFormatter,
        private readonly WorkspaceRepository $workspaceRepository,
        private readonly InboundEmailAddressService $inboundEmailAddress,
    ) {
    }

    /**
     * @return array{id: int|null, email: ?string, displayName: string, firstName: ?string, lastName: ?string, initials: string, gravatarUrl: string, inboxWorkspaceId: int|null, inboxEmailAddress: string|null, inboundAiAutoApply: array{reword: bool, assignee: bool, dueDate: bool, workspace: bool}, inboundRewordLanguage: string, inboundRewordLanguageChoices: list<array{code: string, label: string}>, timezone: string, timezoneChoices: list<array{code: string, label: string}>}
     */
    public function buildUser(User $user): array
    {
        $inboxWorkspace = $this->workspaceRepository->findInboxWorkspaceForUser($user);

        return [
            'id' => $user->getId(),
            'email' => $user->getPublicEmail(),
            'displayName' => $user->getDisplayName(),
            'firstName' => $user->getFirstName(),
            'lastName' => $user->getLastName(),
            'initials' => $user->getInitials(),
            'gravatarUrl' => $this->avatarUrl->resolve($user),
            'inboxWorkspaceId' => $inboxWorkspace?->getId(),
            'inboxEmailAddress' => $this->inboundEmailAddress->buildAddressForUser($user),
            'inboundAiAutoApply' => [
                'reword' => $user->isInboundAutoApplyReword(),
                'assignee' => $user->isInboundAutoApplyAssignee(),
                'dueDate' => $user->isInboundAutoApplyDueDate(),
                'workspace' => $user->isInboundAutoApplyWorkspace(),
            ],
            'inboundRewordLanguage' => $user->getInboundRewordLanguage() ?? 'auto',
            'inboundRewordLanguageChoices' => User::getInboundRewordLanguageChoices(),
            'timezone' => $user->getPreferredTimezone() ?? 'auto',
            'timezoneChoices' => User::getTimezoneChoices(),
        ];
    }

    /**
     * @param list<Workspace> $deletedWorkspaces
     *
     * @return array{user: array<string, mixed>, deletedWorkspaces: list<array<string, mixed>>}
     */
    public function buildProfile(User $user, array $deletedWorkspaces): array
    {
        return [
            'user' => $this->buildUser($user),
            'deletedWorkspaces' => array_map(static fn (Workspace $workspace): array => [
                'id' => $workspace->getId(),
                'name' => $workspace->getName(),
                'deletedAt' => $workspace->getDeletedAt()?->format(\DateTimeInterface::ATOM),
                'deletedAtDisplay' => $this->userDateTimeFormatter->formatDateTime($workspace->getDeletedAt(), $user),
                'isSoloWorkspace' => $workspace->isSoloWorkspace(),
            ], $deletedWorkspaces),
        ];
    }
}
