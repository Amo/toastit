<?php

namespace App\Controller\Api\Profile;

use App\Api\ProfilePayloadBuilder;
use App\Workspace\WorkspaceAccessService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

final class UpdateController extends AbstractController
{
    public function __construct(
        private readonly WorkspaceAccessService $workspaceAccess,
        private readonly EntityManagerInterface $entityManager,
        private readonly ProfilePayloadBuilder $profilePayloadBuilder,
    ) {
    }

    #[Route('/api/profile', name: 'api_profile_update', methods: ['PUT'])]
    public function __invoke(Request $request): JsonResponse
    {
        $user = $this->workspaceAccess->getUserOrFail();
        $payload = $request->toArray();

        $user
            ->setFirstName((string) ($payload['firstName'] ?? '') ?: null)
            ->setLastName((string) ($payload['lastName'] ?? '') ?: null);

        if (is_array($payload['inboundAiAutoApply'] ?? null)) {
            $inboundAiAutoApply = $payload['inboundAiAutoApply'];

            $user
                ->setInboundAutoApplyReword($this->toBool($inboundAiAutoApply['reword'] ?? $user->isInboundAutoApplyReword()))
                ->setInboundAutoApplyAssignee($this->toBool($inboundAiAutoApply['assignee'] ?? $user->isInboundAutoApplyAssignee()))
                ->setInboundAutoApplyDueDate($this->toBool($inboundAiAutoApply['dueDate'] ?? $user->isInboundAutoApplyDueDate()))
                ->setInboundAutoApplyWorkspace($this->toBool($inboundAiAutoApply['workspace'] ?? $user->isInboundAutoApplyWorkspace()));
        }

        $this->entityManager->flush();

        return $this->json([
            'ok' => true,
            'user' => $this->profilePayloadBuilder->buildUser($user),
        ]);
    }

    private function toBool(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_int($value)) {
            return 1 === $value;
        }

        if (is_string($value)) {
            return in_array(strtolower(trim($value)), ['1', 'true', 'on', 'yes'], true);
        }

        return false;
    }
}
