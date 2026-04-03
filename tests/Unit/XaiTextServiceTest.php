<?php

namespace App\Tests\Unit;

use App\Meeting\SessionSummaryUnavailableException;
use App\Meeting\XaiTextService;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

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
            'grok-4.20-reasoning',
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
}
