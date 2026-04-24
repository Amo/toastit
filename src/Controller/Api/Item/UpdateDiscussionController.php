<?php

namespace App\Controller\Api\Item;

use App\Entity\Toast;
use App\Entity\User;
use App\Meeting\SessionSummaryUnavailableException;
use App\Workspace\ToastCreationService;
use App\Workspace\ToastDraftRefinementService;
use App\Workspace\WorkspaceAccessService;
use App\Workspace\WorkspaceWorkflowService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

final class UpdateDiscussionController extends AbstractController
{
    public function __construct(
        private readonly WorkspaceAccessService $workspaceAccess,
        private readonly WorkspaceWorkflowService $workspaceWorkflow,
        private readonly ToastDraftRefinementService $toastDraftRefinement,
        private readonly ToastCreationService $toastCreation,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    #[Route('/api/items/{id}/discussion', name: 'api_item_discussion_update', methods: ['POST'])]
    public function __invoke(int $id, Request $request): JsonResponse
    {
        $item = $this->workspaceAccess->getItemOrFail($id);
        $workspace = $item->getWorkspace();
        $this->workspaceAccess->assertOwner($workspace);
        $this->workspaceAccess->assertMeetingModeActive($workspace);

        $payload = $request->toArray();
        $ownerId = is_numeric($payload['ownerId'] ?? null) ? (int) $payload['ownerId'] : 0;
        $owner = $this->workspaceWorkflow->findWorkspaceInviteeById($workspace, $ownerId);
        $dueAt = null;

        if (!empty($payload['dueOn'])) {
            try {
                $dueAt = new \DateTimeImmutable((string) $payload['dueOn']);
            } catch (\Exception) {
                return $this->json(['ok' => false, 'error' => 'invalid_due_on'], 400);
            }
        }

        $followUpItems = [];
        foreach (($payload['followUpItems'] ?? []) as $followUpItem) {
            $instruction = trim((string) ($followUpItem['title'] ?? ''));

            if ('' === $instruction) {
                continue;
            }

            $followUpOwnerId = is_numeric($followUpItem['ownerId'] ?? null) ? (int) $followUpItem['ownerId'] : 0;
            $followUpOwner = $this->workspaceWorkflow->findWorkspaceInviteeById($workspace, $followUpOwnerId);
            $followUpDueOn = trim((string) ($followUpItem['dueOn'] ?? ''));

            if ('' !== $followUpDueOn) {
                try {
                    new \DateTimeImmutable($followUpDueOn);
                } catch (\Exception) {
                    return $this->json(['ok' => false, 'error' => 'invalid_follow_up_due_on'], 400);
                }
            }

            $followUpItems[] = [
                'instruction' => $instruction,
                'ownerId' => $followUpOwner?->getId(),
                'dueOn' => '' !== $followUpDueOn ? $followUpDueOn : null,
            ];
        }

        $item
            ->setStatus(Toast::STATUS_TOASTED)
            ->setDiscussionNotes(trim((string) ($payload['discussionNotes'] ?? '')) ?: null)
            ->setOwner($owner)
            ->setDueAt($dueAt)
            ->setStatusChangedAt(new \DateTimeImmutable());

        $actor = $this->workspaceAccess->getUserOrFail();

        foreach ($followUpItems as $followUpItem) {
            $followUpOwner = $this->workspaceWorkflow->findWorkspaceInviteeById($workspace, (int) ($followUpItem['ownerId'] ?? 0));
            $followUpDueOn = (string) ($followUpItem['dueOn'] ?? '');
            $followUpDueAt = '' !== $followUpDueOn ? new \DateTimeImmutable($followUpDueOn) : null;
            $instruction = (string) $followUpItem['instruction'];
            $refinedFollowUp = $this->refineFollowUpInstruction($item, $instruction, $actor, $followUpDueOn);
            $refinedOwner = is_numeric($refinedFollowUp['ownerId'] ?? null)
                ? $this->workspaceWorkflow->findWorkspaceInviteeById($workspace, (int) $refinedFollowUp['ownerId'])
                : null;
            $followUpOwner ??= $refinedOwner;

            if (null === $followUpDueAt && !empty($refinedFollowUp['dueOn'])) {
                $followUpDueAt = new \DateTimeImmutable((string) $refinedFollowUp['dueOn']);
            }

            $followUpTitle = trim((string) ($refinedFollowUp['title'] ?? '')) ?: $this->buildFallbackFollowUpTitle($instruction);
            $followUpDescription = trim((string) ($refinedFollowUp['description'] ?? ''));
            if ('' === $followUpDescription) {
                $followUpDescription = sprintf("Follow-up created from \"%s\".\n\n%s", $item->getTitle(), $instruction);
            }

            if ($this->workspaceWorkflow->hasFollowUp($item, $followUpTitle, $followUpOwner, $followUpDueAt)) {
                continue;
            }

            $this->toastCreation->createToast(
                $workspace,
                $actor,
                $followUpTitle,
                $followUpDescription,
                $followUpOwner,
                $followUpDueAt,
                $item,
            );
        }

        $this->entityManager->flush();

        return $this->json(['ok' => true]);
    }

    /**
     * @return array{title: string, description: string, ownerId: ?int, dueOn: ?string}
     */
    private function refineFollowUpInstruction(Toast $sourceToast, string $instruction, User $actor, ?string $dueOn): array
    {
        try {
            return $this->toastDraftRefinement->refine(
                $sourceToast->getWorkspace(),
                '',
                $instruction,
                $actor,
                $dueOn,
            );
        } catch (SessionSummaryUnavailableException) {
            return [
                'title' => $this->buildFallbackFollowUpTitle($instruction),
                'description' => '',
                'ownerId' => null,
                'dueOn' => $dueOn,
            ];
        }
    }

    private function buildFallbackFollowUpTitle(string $instruction): string
    {
        $normalized = preg_replace('/\s+/', ' ', trim($instruction)) ?? trim($instruction);
        if (mb_strlen($normalized) <= 72) {
            return $normalized;
        }

        return mb_substr($normalized, 0, 69).'...';
    }
}
