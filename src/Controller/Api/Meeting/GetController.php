<?php

namespace App\Controller\Api\Meeting;

use App\Api\MeetingPayloadBuilder;
use App\Workspace\WorkspaceAccess;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

final class GetController extends AbstractController
{
    public function __construct(
        private readonly WorkspaceAccess $workspaceAccess,
        private readonly MeetingPayloadBuilder $meetingPayloadBuilder,
    ) {
    }

    #[Route('/api/meetings/{id}', name: 'api_meeting_get', methods: ['GET'])]
    public function __invoke(int $id): JsonResponse
    {
        $meeting = $this->workspaceAccess->getMeetingOrFail($id);
        $currentUser = $this->workspaceAccess->getUserOrFail();

        return $this->json($this->meetingPayloadBuilder->build($meeting, $currentUser));
    }
}
