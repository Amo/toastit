<?php

namespace App\Workspace;

use App\Entity\Toast;
use App\Entity\ToastComment;
use App\Entity\User;
use App\Entity\Workspace;
use Doctrine\ORM\EntityManagerInterface;

final class ToastCurationExecutionService
{
    public function __construct(
        private readonly WorkspaceAccessService $workspaceAccess,
        private readonly WorkspaceWorkflowService $workspaceWorkflow,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * @param list<array<string, mixed>> $actions
     * @return array{applied: list<array<string, mixed>>, skipped: list<array<string, mixed>>}
     */
    public function applyDraft(Workspace $workspace, User $actor, array $actions): array
    {
        $applied = [];
        $skipped = [];

        foreach ($actions as $index => $action) {
            if (!is_array($action)) {
                $skipped[] = ['index' => $index, 'reason' => 'invalid_action'];
                continue;
            }

            $type = (string) ($action['type'] ?? '');
            $toastId = is_numeric($action['toastId'] ?? null) ? (int) $action['toastId'] : 0;
            $toast = $toastId > 0 ? $this->findWorkspaceToast($workspace, $toastId) : null;

            if (null === $toast) {
                $skipped[] = ['index' => $index, 'reason' => 'toast_not_found', 'type' => $type, 'toastId' => $toastId];
                continue;
            }

            if (!$toast->isNew()) {
                $skipped[] = ['index' => $index, 'reason' => 'toast_not_active', 'type' => $type, 'toastId' => $toastId];
                continue;
            }

            $result = match ($type) {
                'update_toast' => $this->applyUpdateToast($workspace, $toast, $action),
                'add_comment' => $this->applyAddComment($actor, $toast, $action),
                'boost_toast' => $this->applyBoostToast($workspace, $toast),
                'veto_toast' => $this->applyVetoToast($toast),
                'create_follow_up' => $this->applyCreateFollowUp($workspace, $actor, $toast, $action),
                default => null,
            };

            if (null === $result) {
                $skipped[] = ['index' => $index, 'reason' => 'unsupported_or_invalid', 'type' => $type, 'toastId' => $toastId];
                continue;
            }

            $applied[] = ['index' => $index] + $result;
        }

        $this->entityManager->flush();

        return [
            'applied' => $applied,
            'skipped' => $skipped,
        ];
    }

    private function findWorkspaceToast(Workspace $workspace, int $toastId): ?Toast
    {
        foreach ($workspace->getItems() as $item) {
            if ($item->getId() === $toastId) {
                return $item;
            }
        }

        return null;
    }

    /**
     * @param array<string, mixed> $action
     * @return array<string, mixed>|null
     */
    private function applyUpdateToast(Workspace $workspace, Toast $toast, array $action): ?array
    {
        $changed = false;

        if (isset($action['title'])) {
            $title = trim((string) $action['title']);
            if ('' === $title) {
                return null;
            }
            $toast->setTitle($title);
            $changed = true;
        }

        if (array_key_exists('description', $action)) {
            $toast->setDescription(trim((string) ($action['description'] ?? '')) ?: null);
            $changed = true;
        }

        if (array_key_exists('ownerId', $action)) {
            $ownerId = is_numeric($action['ownerId'] ?? null) ? (int) $action['ownerId'] : 0;
            $toast->setOwner($this->workspaceWorkflow->findWorkspaceInviteeById($workspace, $ownerId));
            $changed = true;
        }

        if (array_key_exists('dueOn', $action)) {
            $dueOn = trim((string) ($action['dueOn'] ?? ''));
            if ('' === $dueOn) {
                $toast->setDueAt(null);
            } else {
                try {
                    $toast->setDueAt(new \DateTimeImmutable($dueOn));
                } catch (\Exception) {
                    return null;
                }
            }
            $changed = true;
        }

        return $changed ? [
            'type' => 'update_toast',
            'toastId' => $toast->getId(),
        ] : null;
    }

    /**
     * @param array<string, mixed> $action
     * @return array<string, mixed>|null
     */
    private function applyAddComment(User $actor, Toast $toast, array $action): ?array
    {
        $content = trim((string) ($action['content'] ?? ''));
        if ('' === $content) {
            return null;
        }

        $comment = (new ToastComment())
            ->setToast($toast)
            ->setAuthor($actor)
            ->setContent($content);

        $toast->addComment($comment);
        $this->entityManager->persist($comment);

        return [
            'type' => 'add_comment',
            'toastId' => $toast->getId(),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function applyBoostToast(Workspace $workspace, Toast $toast): ?array
    {
        if ($toast->isBoosted()) {
            return null;
        }

        if ($toast->isVetoed()) {
            $toast->setStatus(Toast::STATUS_PENDING);
        }

        $toast
            ->setIsBoosted(true)
            ->setBoostRank($this->workspaceWorkflow->nextBoostRank($workspace));

        return [
            'type' => 'boost_toast',
            'toastId' => $toast->getId(),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function applyVetoToast(Toast $toast): ?array
    {
        if ($toast->isVetoed()) {
            return null;
        }

        $toast
            ->setStatus(Toast::STATUS_DISCARDED)
            ->setIsBoosted(false)
            ->setStatusChangedAt(new \DateTimeImmutable());

        return [
            'type' => 'veto_toast',
            'toastId' => $toast->getId(),
        ];
    }

    /**
     * @param array<string, mixed> $action
     * @return array<string, mixed>|null
     */
    private function applyCreateFollowUp(Workspace $workspace, User $actor, Toast $sourceToast, array $action): ?array
    {
        $title = trim((string) ($action['title'] ?? ''));
        if ('' === $title) {
            return null;
        }

        $ownerId = is_numeric($action['ownerId'] ?? null) ? (int) $action['ownerId'] : 0;
        $owner = $this->workspaceWorkflow->findWorkspaceInviteeById($workspace, $ownerId);
        $dueAt = null;
        $dueOn = trim((string) ($action['dueOn'] ?? ''));

        if ('' !== $dueOn) {
            try {
                $dueAt = new \DateTimeImmutable($dueOn);
            } catch (\Exception) {
                return null;
            }
        }

        if ($this->workspaceWorkflow->hasFollowUp($sourceToast, $title, $owner, $dueAt)) {
            return null;
        }

        $nextItem = (new Toast())
            ->setWorkspace($workspace)
            ->setAuthor($actor)
            ->setTitle($title)
            ->setDescription(trim((string) ($action['description'] ?? '')) ?: sprintf('Follow-up created from "%s".', $sourceToast->getTitle()))
            ->setOwner($owner)
            ->setDueAt($dueAt)
            ->setPreviousItem($sourceToast);

        $this->entityManager->persist($nextItem);

        return [
            'type' => 'create_follow_up',
            'toastId' => $sourceToast->getId(),
            'createdToastTitle' => $nextItem->getTitle(),
        ];
    }
}
