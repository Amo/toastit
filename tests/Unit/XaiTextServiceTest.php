<?php

namespace App\Tests\Unit;

use App\Entity\User;
use App\Meeting\SessionSummaryUnavailableException;
use App\Meeting\XaiTextService;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Contracts\HttpClient\Exception\TimeoutExceptionInterface;

final class XaiTextServiceTest extends TestCase
{
    public function testGenerateSummaryExtractsOutputTextFromResponsesApiPayload(): void
    {
        $service = new XaiTextService(
            new MockHttpClient([
                new MockResponse(json_encode([
                    'output' => [[
                        'content' => [[
                            'type' => 'output_text',
                            'text' => "## Decisions\n- Ship the recap",
                        ]],
                    ]],
                ], JSON_THROW_ON_ERROR)),
            ]),
            'test-key',
            'https://api.x.ai/v1',
            'grok-4.20-0309-non-reasoning',
            30,
        );

        $summary = $service->generateSummary('system', 'user');

        self::assertStringContainsString('## Decisions', $summary);
        self::assertStringContainsString('Ship the recap', $summary);
    }

    public function testGenerateSummaryFailsWhenApiKeyIsMissing(): void
    {
        $service = new XaiTextService(
            new MockHttpClient(),
            '',
            'https://api.x.ai/v1',
            'grok-4.20-reasoning',
            30,
        );

        $this->expectException(SessionSummaryUnavailableException::class);
        $this->expectExceptionMessage('xAI is not configured.');

        $service->generateSummary('system', 'user');
    }

    public function testGenerateSummaryRetriesOnceAfterTimeoutAndReturnsContent(): void
    {
        $requestCount = 0;
        $service = new XaiTextService(
            new MockHttpClient(static function () use (&$requestCount) {
                ++$requestCount;

                if (1 === $requestCount) {
                    throw new class('request timed out') extends \RuntimeException implements TimeoutExceptionInterface {
                    };
                }

                return new MockResponse(json_encode([
                    'output' => [[
                        'content' => [[
                            'type' => 'output_text',
                            'text' => 'Recovered summary after retry',
                        ]],
                    ]],
                ], JSON_THROW_ON_ERROR));
            }),
            'test-key',
            'https://api.x.ai/v1',
            'grok-4.20-reasoning',
            30,
        );

        $summary = $service->generateSummary('system', 'user');

        self::assertSame(2, $requestCount);
        self::assertStringContainsString('Recovered summary after retry', $summary);
    }

    public function testGenerateSummaryReturnsTimeoutReasonAfterRetryExhausted(): void
    {
        $service = new XaiTextService(
            new MockHttpClient(static function () {
                throw new class('request timed out') extends \RuntimeException implements TimeoutExceptionInterface {
                };
            }),
            'test-key',
            'https://api.x.ai/v1',
            'grok-4.20-reasoning',
            30,
        );

        try {
            $service->generateSummary('system', 'user');
            self::fail('Expected SessionSummaryUnavailableException was not thrown.');
        } catch (SessionSummaryUnavailableException $exception) {
            self::assertSame('xai_request_timeout', $exception->getReason());
            self::assertSame('xAI request timed out.', $exception->getMessage());
        }
    }

    public function testGenerateTextForUserMapsLegacyAdvancedNonReasoningAliasToCurrentModelName(): void
    {
        $capturedModel = null;
        $service = new XaiTextService(
            new MockHttpClient(static function (string $method, string $url, array $options) use (&$capturedModel) {
                $payload = json_decode((string) ($options['body'] ?? ''), true);
                $capturedModel = is_array($payload) ? ($payload['model'] ?? null) : null;

                return new MockResponse(json_encode([
                    'output' => [[
                        'content' => [[
                            'type' => 'output_text',
                            'text' => 'OK',
                        ]],
                    ]],
                ], JSON_THROW_ON_ERROR));
            }),
            'test-key',
            'https://api.x.ai/v1',
            'grok-4-1-fast-non-reasoning',
            30,
            'grok-4.20-non-reasoning',
        );

        $user = (new User())
            ->setEmail('amaury@example.com')
            ->setAdvancedAiModelEnabled(true);

        self::assertSame('OK', $service->generateTextForUser($user, 'system', 'user'));
        self::assertSame('grok-4.20-0309-non-reasoning', $capturedModel);
    }
}
