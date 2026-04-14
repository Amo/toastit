<?php

namespace App\Controller\Api\Profile;

use App\Api\ProfilePayloadBuilder;
use App\Entity\User;
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

        if (array_key_exists('inboundRewordLanguage', $payload)) {
            $language = $payload['inboundRewordLanguage'];
            if (!is_string($language)) {
                return $this->json([
                    'ok' => false,
                    'error' => 'invalid_inbound_reword_language',
                ], 400);
            }

            $normalized = strtolower(trim($language));
            if ('' === $normalized || 'auto' === $normalized) {
                $user->setInboundRewordLanguage(null);
            } elseif (User::isSupportedInboundRewordLanguage($normalized)) {
                $user->setInboundRewordLanguage($normalized);
            } else {
                return $this->json([
                    'ok' => false,
                    'error' => 'invalid_inbound_reword_language',
                ], 400);
            }
        }

        if (array_key_exists('timezone', $payload)) {
            $timezone = $payload['timezone'];
            if (!is_string($timezone)) {
                return $this->json([
                    'ok' => false,
                    'error' => 'invalid_timezone',
                ], 400);
            }

            $normalizedTimezone = trim($timezone);
            if ('' === $normalizedTimezone || 'auto' === strtolower($normalizedTimezone)) {
                $user->setPreferredTimezone(null);
            } elseif (User::isSupportedTimezone($normalizedTimezone)) {
                $user->setPreferredTimezone($normalizedTimezone);
            } else {
                return $this->json([
                    'ok' => false,
                    'error' => 'invalid_timezone',
                ], 400);
            }
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
