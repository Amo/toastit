<?php

namespace App\Controller\App\Item;

use App\Entity\Toast;
use App\Workspace\WorkspaceAccessService;
use App\Workspace\WorkspaceWorkflowService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

final class UpdateDiscussionController extends AbstractController
{
    public function __construct(
        private readonly WorkspaceAccessService $workspaceAccess,
        private readonly WorkspaceWorkflowService $workspaceWorkflow,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    #[Route('/app/items/{id}/discussion', name: 'app_item_discussion_update', methods: ['POST'])]
    public function __invoke(int $id, Request $request): RedirectResponse
    {
        $item = $this->workspaceAccess->getItemOrFail($id);
        $workspace = $item->getWorkspace();
        $this->workspaceAccess->assertOwner($workspace);
        $this->workspaceAccess->assertMeetingModeActive($workspace);

        $ownerIdRaw = trim($request->request->getString('owner_id'));
        $ownerId = ctype_digit($ownerIdRaw) ? (int) $ownerIdRaw : 0;
        $owner = $this->workspaceWorkflow->findWorkspaceInviteeById($workspace, $ownerId);
        $dueAtRaw = trim($request->request->getString('due_at'));
        $dueAt = null;

        if ('' !== $dueAtRaw) {
            try {
                $dueAt = new \DateTimeImmutable($dueAtRaw);
            } catch (\Exception) {
                $this->addFlash('error', 'The due date is invalid.');

                return $this->redirectToRoute('app_workspace_show', ['id' => $workspace->getId()]);
            }
        }

        $followUpTitles = $request->request->all('follow_up_titles');
        $followUpOwnerIds = $request->request->all('follow_up_owner_ids');
        $followUpDueOn = $request->request->all('follow_up_due_on');
        $followUpItems = [];

        foreach ($followUpTitles as $index => $followUpTitleRaw) {
            $title = trim((string) $followUpTitleRaw);

            if ('' === $title) {
                continue;
            }

            $followUpOwnerRaw = trim((string) ($followUpOwnerIds[$index] ?? ''));
            $followUpOwnerId = ctype_digit($followUpOwnerRaw) ? (int) $followUpOwnerRaw : 0;
            $followUpOwner = $this->workspaceWorkflow->findWorkspaceInviteeById($workspace, $followUpOwnerId);
            $followUpDueOnRaw = trim((string) ($followUpDueOn[$index] ?? ''));

            if ('' !== $followUpDueOnRaw) {
                try {
                    new \DateTimeImmutable($followUpDueOnRaw);
                } catch (\Exception) {
                    $this->addFlash('error', 'A follow-up date is invalid.');

                    return $this->redirectToRoute('app_workspace_show', ['id' => $workspace->getId()]);
                }
            }

            $followUpItems[] = [
                'title' => $title,
                'ownerId' => $followUpOwner?->getId(),
                'dueOn' => '' !== $followUpDueOnRaw ? $followUpDueOnRaw : null,
            ];
        }

        $item
            ->setDiscussionStatus(Toast::DISCUSSION_TREATED)
            ->setDiscussionNotes(trim($request->request->getString('discussion_notes')) ?: null)
            ->setOwner($owner)
            ->setDueAt($dueAt)
            ->setStatusChangedAt(new \DateTimeImmutable());

        foreach ($followUpItems as $followUpItem) {
            $followUpOwner = $this->workspaceWorkflow->findWorkspaceInviteeById($workspace, (int) ($followUpItem['ownerId'] ?? 0));
            $followUpDueAt = !empty($followUpItem['dueOn']) ? new \DateTimeImmutable((string) $followUpItem['dueOn']) : null;

            if ($this->workspaceWorkflow->hasFollowUp($item, $followUpItem['title'], $followUpOwner, $followUpDueAt)) {
                continue;
            }

            $nextItem = (new Toast())
                ->setWorkspace($workspace)
                ->setAuthor($this->workspaceAccess->getUserOrFail())
                ->setTitle($followUpItem['title'])
                ->setOwner($followUpOwner)
                ->setDueAt($followUpDueAt)
                ->setPreviousItem($item)
                ->setDescription(sprintf('Follow-up created from "%s".', $item->getTitle()));

            $this->entityManager->persist($nextItem);
        }

        $this->entityManager->flush();
        $this->addFlash('success', sprintf('Follow-up details for toast "%s" were updated.', $item->getTitle()));

        return $this->redirectToRoute('app_workspace_show', ['id' => $workspace->getId()]);
    }
}
