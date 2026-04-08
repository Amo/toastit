<?php

namespace App\Security;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\RateLimiter\RateLimiterFactory;

final class AuthRateLimitService
{
    public function __construct(
        #[Autowire(service: 'limiter.auth_request_ip')]
        private readonly RateLimiterFactory $authRequestIpLimiter,
        #[Autowire(service: 'limiter.auth_request_email')]
        private readonly RateLimiterFactory $authRequestEmailLimiter,
        #[Autowire(service: 'limiter.auth_verify_ip')]
        private readonly RateLimiterFactory $authVerifyIpLimiter,
        #[Autowire(service: 'limiter.auth_verify_email')]
        private readonly RateLimiterFactory $authVerifyEmailLimiter,
    ) {
    }

    public function allowOtpRequest(Request $request, string $email): bool
    {
        return $this->consume($this->authRequestIpLimiter, $this->resolveClientKey($request))
            && $this->consume($this->authRequestEmailLimiter, $this->normalizeEmailKey($email));
    }

    public function allowOtpVerify(Request $request, string $email): bool
    {
        return $this->consume($this->authVerifyIpLimiter, $this->resolveClientKey($request))
            && $this->consume($this->authVerifyEmailLimiter, $this->normalizeEmailKey($email));
    }

    private function consume(RateLimiterFactory $factory, string $key): bool
    {
        return $factory->create($key)->consume()->isAccepted();
    }

    private function normalizeEmailKey(string $email): string
    {
        $normalized = mb_strtolower(trim($email));

        return '' !== $normalized ? $normalized : 'empty-email';
    }

    private function resolveClientKey(Request $request): string
    {
        $forwardedFor = trim((string) $request->headers->get('X-Forwarded-For'));
        if ('' !== $forwardedFor) {
            $parts = array_map('trim', explode(',', $forwardedFor));
            if ('' !== ($parts[0] ?? '')) {
                return $parts[0];
            }
        }

        return $request->getClientIp() ?? 'unknown-client';
    }
}
