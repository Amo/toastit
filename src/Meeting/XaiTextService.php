<?php

namespace App\Meeting;

use App\Security\AppEventLogger;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TimeoutExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class XaiTextService
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly string $apiKey,
        private readonly string $baseUrl,
        private readonly string $model,
        private readonly int $timeoutSeconds,
        private readonly ?AppEventLogger $eventLogger = null,
        private readonly ?RequestStack $requestStack = null,
    ) {
    }

    public function isConfigured(): bool
    {
        return '' !== trim($this->apiKey);
    }

    /**
     * @param array{
     *   source?: string,
     *   userId?: int|null,
     *   workspaceId?: int|null,
     *   toastId?: int|null,
     *   sessionId?: int|null,
     *   requestId?: string|null
     * }|null $context
     */
    public function generateText(string $systemPrompt, string $userPrompt, ?array $context = null): string
    {
        $source = $context['source'] ?? 'xai';
        $userId = isset($context['userId']) ? (int) $context['userId'] : null;
        $workspaceId = isset($context['workspaceId']) ? (int) $context['workspaceId'] : null;
        $toastId = isset($context['toastId']) ? (int) $context['toastId'] : null;
        $sessionId = isset($context['sessionId']) ? (int) $context['sessionId'] : null;
        $requestId = isset($context['requestId']) ? trim((string) $context['requestId']) : null;
        $requestId = '' === $requestId ? $this->resolveRequestId() : $requestId;

        if (!$this->isConfigured()) {
            $this->logEvent($userId, $source, 'not_configured', [
                'workspaceId' => $workspaceId,
                'toastId' => $toastId,
                'sessionId' => $sessionId,
                'requestId' => $requestId,
            ]);
            throw new SessionSummaryUnavailableException('xai_not_configured', 'xAI is not configured.');
        }

        $maxRetries = 1;
        $lastError = null;

        for ($attempt = 1; $attempt <= $maxRetries + 1; ++$attempt) {
            try {
                $response = $this->httpClient->request('POST', rtrim($this->baseUrl, '/').'/responses', [
                    'timeout' => $this->timeoutSeconds,
                    'max_duration' => $this->timeoutSeconds,
                    'headers' => [
                        'Authorization' => sprintf('Bearer %s', $this->apiKey),
                        'Content-Type' => 'application/json',
                        'Accept' => 'application/json',
                    ],
                    'json' => [
                        'model' => $this->model,
                        'store' => false,
                        'input' => [
                            [
                                'role' => 'system',
                                'content' => $systemPrompt,
                            ],
                            [
                                'role' => 'user',
                                'content' => $userPrompt,
                            ],
                        ],
                    ],
                ]);

                $statusCode = $response->getStatusCode();
                if ($statusCode >= 400) {
                    $shouldRetry = $attempt <= $maxRetries && (429 === $statusCode || $statusCode >= 500);
                    if ($shouldRetry) {
                        usleep(200000 * $attempt);
                        continue;
                    }

                    $this->logEvent($userId, $source, 'failed', [
                        'workspaceId' => $workspaceId,
                        'toastId' => $toastId,
                        'sessionId' => $sessionId,
                        'requestId' => $requestId,
                        'attempt' => $attempt,
                        'statusCode' => $statusCode,
                    ]);
                    throw new SessionSummaryUnavailableException('xai_request_failed', 'xAI returned an error response.');
                }

                $payload = $response->toArray(false);
                if (!is_array($payload)) {
                    $this->logEvent($userId, $source, 'failed', [
                        'workspaceId' => $workspaceId,
                        'toastId' => $toastId,
                        'sessionId' => $sessionId,
                        'requestId' => $requestId,
                        'attempt' => $attempt,
                        'statusCode' => $statusCode,
                        'error' => 'invalid_payload',
                    ]);
                    throw new SessionSummaryUnavailableException('xai_empty_response', 'xAI returned an invalid response.');
                }

                $text = $this->extractOutputText($payload);

                if ('' === $text) {
                    $this->logEvent($userId, $source, 'failed', [
                        'workspaceId' => $workspaceId,
                        'toastId' => $toastId,
                        'sessionId' => $sessionId,
                        'requestId' => $requestId,
                        'attempt' => $attempt,
                        'statusCode' => $statusCode,
                        'error' => 'empty_output',
                    ]);
                    throw new SessionSummaryUnavailableException('xai_empty_response', 'xAI returned an empty summary.');
                }

                $this->logEvent($userId, $source, 'succeeded', [
                    'workspaceId' => $workspaceId,
                    'toastId' => $toastId,
                    'sessionId' => $sessionId,
                    'requestId' => $requestId,
                    'attempt' => $attempt,
                    'statusCode' => $statusCode,
                ]);

                return $text;
            } catch (ExceptionInterface $exception) {
                $lastError = $exception;
                $isTimeout = $exception instanceof TimeoutExceptionInterface;
                $shouldRetry = $attempt <= $maxRetries && $isTimeout;
                if ($shouldRetry) {
                    usleep(200000 * $attempt);
                    continue;
                }

                $this->logEvent($userId, $source, 'failed', [
                    'workspaceId' => $workspaceId,
                    'toastId' => $toastId,
                    'sessionId' => $sessionId,
                    'requestId' => $requestId,
                    'attempt' => $attempt,
                    'errorClass' => $exception::class,
                    'error' => $isTimeout ? 'timeout' : 'transport',
                ]);

                if ($isTimeout) {
                    throw new SessionSummaryUnavailableException('xai_request_timeout', 'xAI request timed out.', $exception);
                }

                throw new SessionSummaryUnavailableException('xai_request_failed', 'Unable to contact xAI.', $exception);
            }
        }

        throw new SessionSummaryUnavailableException('xai_request_failed', 'Unable to contact xAI.', $lastError);
    }

    /**
     * @param array{
     *   source?: string,
     *   userId?: int|null,
     *   workspaceId?: int|null,
     *   toastId?: int|null,
     *   sessionId?: int|null,
     *   requestId?: string|null
     * }|null $context
     */
    public function generateSummary(string $systemPrompt, string $userPrompt, ?array $context = null): string
    {
        return $this->generateText($systemPrompt, $userPrompt, $context);
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function extractOutputText(array $payload): string
    {
        $chunks = [];

        if (isset($payload['output']) && is_array($payload['output'])) {
            foreach ($payload['output'] as $output) {
                if (!is_array($output)) {
                    continue;
                }

                $content = $output['content'] ?? null;
                if (!is_array($content)) {
                    continue;
                }

                foreach ($content as $part) {
                    if (!is_array($part) || ($part['type'] ?? null) !== 'output_text') {
                        continue;
                    }

                    $text = trim((string) ($part['text'] ?? ''));
                    if ('' !== $text) {
                        $chunks[] = $text;
                    }
                }
            }
        }

        if (isset($payload['output_text']) && is_string($payload['output_text']) && '' !== trim($payload['output_text'])) {
            $chunks[] = trim($payload['output_text']);
        }

        return trim(implode("\n\n", array_values(array_unique($chunks))));
    }

    /**
     * @param array<string, mixed> $metadata
     */
    private function logEvent(?int $userId, string $source, string $status, array $metadata = []): void
    {
        if (!$this->eventLogger instanceof AppEventLogger) {
            return;
        }

        $this->eventLogger->log('xai.call', $userId, null, $source, $status, $metadata);
    }

    private function resolveRequestId(): ?string
    {
        if (!$this->requestStack instanceof RequestStack) {
            return null;
        }

        $request = $this->requestStack->getCurrentRequest();
        if (null === $request) {
            return null;
        }

        $requestId = $request->headers->get('X-Request-Id');

        if (!is_string($requestId)) {
            return null;
        }

        $requestId = trim($requestId);

        return '' === $requestId ? null : $requestId;
    }
}
