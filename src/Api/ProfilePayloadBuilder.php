<?php

namespace App\Api;

use App\Entity\User;
use App\Entity\Workspace;
use App\Profile\AvatarUrlService;
use App\Profile\UserDateTimeFormatter;
use App\Release\AppVersionProvider;
use App\Release\ChangelogHtmlProvider;
use App\Repository\WorkspaceRepository;
use App\Workspace\InboundEmailAddressService;

final class ProfilePayloadBuilder
{
    public function __construct(
        private readonly AvatarUrlService $avatarUrl,
        private readonly UserDateTimeFormatter $userDateTimeFormatter,
        private readonly WorkspaceRepository $workspaceRepository,
        private readonly InboundEmailAddressService $inboundEmailAddress,
        private readonly AppVersionProvider $appVersionProvider,
        private readonly ChangelogHtmlProvider $changelogHtmlProvider,
    ) {
    }

    /**
     * @return array{id: int|null, email: ?string, displayName: string, firstName: ?string, lastName: ?string, initials: string, gravatarUrl: string, isRoot: bool, isRoute: bool, advancedAiModelEnabled: bool, inboxWorkspaceId: int|null, inboxEmailAddress: string|null, inboundAiAutoApply: array{reword: bool, assignee: bool, dueDate: bool, workspace: bool}, inboundRewordLanguage: string, inboundRewordLanguageChoices: list<array{code: string, label: string}>, timezone: string, timezoneChoices: list<array{code: string, label: string}>}
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
            'isRoot' => $user->isRoot(),
            'isRoute' => $user->isRoute(),
            'advancedAiModelEnabled' => $user->isAdvancedAiModelEnabled(),
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
     * @return array{
     *     user: array<string, mixed>,
     *     about: array{appVersion: string, changelogHtml: string},
     *     deletedWorkspaces: list<array<string, mixed>>
     * }
     */
    public function buildProfile(User $user, array $deletedWorkspaces): array
    {
        $userDateTimeFormatter = $this->userDateTimeFormatter;

        return [
            'user' => $this->buildUser($user),
            'about' => [
                'appVersion' => $this->appVersionProvider->getCurrentVersion(),
                'changelogHtml' => $this->changelogHtmlProvider->getCurrentHtml(),
            ],
            'deletedWorkspaces' => array_map(static fn (Workspace $workspace): array => [
                'id' => $workspace->getId(),
                'name' => $workspace->getName(),
                'deletedAt' => $workspace->getDeletedAt()?->format(\DateTimeInterface::ATOM),
                'deletedAtDisplay' => $userDateTimeFormatter->formatDateTime($workspace->getDeletedAt(), $user),
                'isSoloWorkspace' => $workspace->isSoloWorkspace(),
            ], $deletedWorkspaces),
        ];
    }
}
