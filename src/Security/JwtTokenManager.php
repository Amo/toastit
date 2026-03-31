<?php

namespace App\Security;

use App\Entity\User;

final class JwtTokenManager
{
    public function __construct(
        private readonly string $secret,
    ) {
    }

    public function createAccessToken(User $user, \DateTimeImmutable $now): string
    {
        return $this->encode([
            'typ' => 'access',
            'sub' => $user->getId(),
            'email' => $user->getEmail(),
            'roles' => $user->getRoles(),
            'iat' => $now->getTimestamp(),
            'exp' => $now->modify('+30 minutes')->getTimestamp(),
        ]);
    }

    public function createPinSetupToken(User $user, \DateTimeImmutable $now): string
    {
        return $this->encode([
            'typ' => 'pin_setup',
            'sub' => $user->getId(),
            'email' => $user->getEmail(),
            'iat' => $now->getTimestamp(),
            'exp' => $now->modify('+15 minutes')->getTimestamp(),
        ]);
    }

    public function decode(string $token): ?array
    {
        $parts = explode('.', $token);

        if (3 !== count($parts)) {
            return null;
        }

        [$encodedHeader, $encodedPayload, $signature] = $parts;
        $expectedSignature = $this->base64UrlEncode(hash_hmac('sha256', $encodedHeader.'.'.$encodedPayload, $this->secret, true));

        if (!hash_equals($expectedSignature, $signature)) {
            return null;
        }

        $payload = json_decode($this->base64UrlDecode($encodedPayload), true);

        if (!is_array($payload)) {
            return null;
        }

        if (!isset($payload['exp']) || !is_numeric($payload['exp']) || (int) $payload['exp'] <= time()) {
            return null;
        }

        return $payload;
    }

    private function encode(array $payload): string
    {
        $header = ['alg' => 'HS256', 'typ' => 'JWT'];
        $encodedHeader = $this->base64UrlEncode(json_encode($header, JSON_THROW_ON_ERROR));
        $encodedPayload = $this->base64UrlEncode(json_encode($payload, JSON_THROW_ON_ERROR));
        $signature = $this->base64UrlEncode(hash_hmac('sha256', $encodedHeader.'.'.$encodedPayload, $this->secret, true));

        return $encodedHeader.'.'.$encodedPayload.'.'.$signature;
    }

    private function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    private function base64UrlDecode(string $value): string
    {
        $padding = strlen($value) % 4;

        if ($padding > 0) {
            $value .= str_repeat('=', 4 - $padding);
        }

        return base64_decode(strtr($value, '-_', '+/'), true) ?: '';
    }
}
