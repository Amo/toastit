<?php

namespace App\Security;

use Google\Cloud\RecaptchaEnterprise\V1\Assessment;
use Google\Cloud\RecaptchaEnterprise\V1\Client\RecaptchaEnterpriseServiceClient;
use Google\Cloud\RecaptchaEnterprise\V1\CreateAssessmentRequest;
use Google\Cloud\RecaptchaEnterprise\V1\Event;

final class RecaptchaVerifierService
{
    public function __construct(
        private readonly ?string $siteKey,
        private readonly ?string $projectId,
        private readonly float $minimumScore,
    ) {
    }

    public function isEnabled(): bool
    {
        return '' !== trim((string) $this->siteKey)
            && '' !== trim((string) $this->projectId);
    }

    public function verifyV3(string $token, string $expectedAction, ?string $remoteIp = null): bool
    {
        if (!$this->isEnabled()) {
            return true;
        }

        if ('' === trim($token)) {
            return false;
        }

        $event = (new Event())
            ->setSiteKey((string) $this->siteKey)
            ->setToken($token)
            ->setExpectedAction($expectedAction);

        if (null !== $remoteIp && '' !== trim($remoteIp)) {
            $event->setUserIpAddress($remoteIp);
        }

        $assessment = (new Assessment())
            ->setEvent($event);

        try {
            $client = new RecaptchaEnterpriseServiceClient([
                // Avoid requiring grpc extension; use REST transport with ADC credentials.
                'transport' => 'rest',
            ]);
            $request = (new CreateAssessmentRequest())
                ->setParent($client->projectName((string) $this->projectId))
                ->setAssessment($assessment);
            $response = $client->createAssessment($request);
        } catch (\Throwable) {
            return false;
        }

        $tokenProperties = $response->getTokenProperties();
        if (!$tokenProperties->getValid()) {
            return false;
        }

        if ($tokenProperties->getAction() !== $expectedAction) {
            return false;
        }

        return $response->getRiskAnalysis()->getScore() >= $this->minimumScore;
    }
}
