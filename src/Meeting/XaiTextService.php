<?php

namespace App\Meeting;

use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class XaiTextService
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly string $apiKey,
        private readonly string $baseUrl,
        private readonly string $model,
        private readonly int $timeoutSeconds,
    ) {
    }

    public function isConfigured(): bool
    {
        return '' !== trim($this->apiKey);
    }

    public function generateText(string $systemPrompt, string $userPrompt): string
    {
        if (!$this->isConfigured()) {
            throw new SessionSummaryUnavailableException('xai_not_configured', 'xAI is not configured.');
        }

        try {
            $response = $this->httpClient->request('POST', rtrim($this->baseUrl, '/').'/responses', [
                'timeout' => $this->timeoutSeconds,
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

            if ($response->getStatusCode() >= 400) {
                throw new SessionSummaryUnavailableException('xai_request_failed', 'xAI returned an error response.');
            }

            $payload = $response->toArray(false);
            if (!is_array($payload)) {
                throw new SessionSummaryUnavailableException('xai_empty_response', 'xAI returned an invalid response.');
            }
        } catch (ExceptionInterface $exception) {
            throw new SessionSummaryUnavailableException('xai_request_failed', 'Unable to contact xAI.', $exception);
        }

        $text = $this->extractOutputText($payload);

        if ('' === $text) {
            throw new SessionSummaryUnavailableException('xai_empty_response', 'xAI returned an empty summary.');
        }

        return $text;
    }

    public function generateSummary(string $systemPrompt, string $userPrompt): string
    {
        return $this->generateText($systemPrompt, $userPrompt);
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
}
