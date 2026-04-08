<?php

namespace App\Controller\Api\Inbound;

use App\Workspace\InboundEmailService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

final class ConfirmActionController extends AbstractController
{
    public function __construct(
        private readonly InboundEmailService $inboundEmailService,
    ) {
    }

    #[Route('/api/inbound/action/{token}/confirm', name: 'api_inbound_action_confirm', methods: ['GET'])]
    public function __invoke(string $token): JsonResponse
    {
        $result = $this->inboundEmailService->applyConfirmationToken($token);

        return $this->json($result, $result['ok'] ? 200 : 400);
    }
}
