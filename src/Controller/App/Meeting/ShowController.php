<?php

namespace App\Controller\App\Meeting;

use App\Meeting\MeetingAgendaBuilder;
use App\Security\JwtTokenManager;
use App\Workspace\MeetingWorkflow;
use App\Workspace\WorkspaceAccess;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class ShowController extends AbstractController
{
    public function __construct(
        private readonly WorkspaceAccess $workspaceAccess,
        private readonly MeetingAgendaBuilder $meetingAgendaBuilder,
        private readonly MeetingWorkflow $meetingWorkflow,
        private readonly JwtTokenManager $jwtTokenManager,
    ) {
    }

    #[Route('/app/meetings/{id}', name: 'app_meeting_show', methods: ['POST'])]
    public function __invoke(int $id): Response
    {
        throw new \LogicException('GET handled by SPA shell.');
    }
}
