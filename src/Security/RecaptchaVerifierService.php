<?php

namespace App\Security;

use Symfony\Contracts\HttpClient\HttpClientInterface;

final class RecaptchaVerifierService
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly string $siteKey,
        private readonly string $projectId,
        private readonly string $apiKey,
        private readonly float $minimumScore,
    ) {
    }

    public function isEnabled(): bool
    {
        return '' !== trim($this->siteKey)
            && '' !== trim($this->projectId)
            && '' !== trim($this->apiKey);
    }

    public function verifyV3(string $token, string $expectedAction, ?string $remoteIp = null): bool
    {
        if (!$this->isEnabled()) {
            return true;
        }

        if ('' === trim($token)) {
            return false;
        }

        $event = [
            'token' => $token,
            'siteKey' => $this->siteKey,
            'expectedAction' => $expectedAction,
        ];

        if (null !== $remoteIp && '' !== trim($remoteIp)) {
            $event['userIpAddress'] = $remoteIp;
        }

        $url = sprintf(
            'https://recaptchaenterprise.googleapis.com/v1/projects/%s/assessments?key=%s',
            rawurlencode($this->projectId),
            rawurlencode($this->apiKey),
        );

        try {
            $response = $this->httpClient->request('POST', $url, [
                'json' => [
                    'event' => $event,
                ],
            ]);
            $payload = $response->toArray(false);
        } catch (\Throwable) {
            return false;
        }

        if (!($payload['tokenProperties']['valid'] ?? false)) {
            return false;
        }

        if (($payload['tokenProperties']['action'] ?? '') !== $expectedAction) {
            return false;
        }

        return (float) ($payload['riskAnalysis']['score'] ?? 0.0) >= $this->minimumScore;
    }
}
