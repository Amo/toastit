<?php

namespace App\Security;

use App\Repository\UserRepository;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\Authenticator\AbstractAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;

final class AccessTokenAuthenticator extends AbstractAuthenticator
{
    public function __construct(
        private readonly JwtTokenService $jwtTokenManager,
        private readonly UserRepository $userRepository,
    ) {
    }

    public function supports(Request $request): ?bool
    {
        return str_starts_with($request->getPathInfo(), '/api/')
            && !str_starts_with($request->getPathInfo(), '/api/auth/')
            && $request->headers->has('Authorization');
    }

    public function authenticate(Request $request): SelfValidatingPassport
    {
        $authorization = (string) $request->headers->get('Authorization');

        if (!str_starts_with($authorization, 'Bearer ')) {
            throw new AuthenticationException('Missing bearer token.');
        }

        $payload = $this->jwtTokenManager->decode(substr($authorization, 7));

        if (!is_array($payload) || ($payload['typ'] ?? null) !== 'access' || !isset($payload['sub'])) {
            throw new AuthenticationException('Invalid access token.');
        }

        return new SelfValidatingPassport(new UserBadge((string) $payload['sub'], function (string $userId) {
            $user = $this->userRepository->find((int) $userId);

            if (null === $user || $user->isDeleted()) {
                throw new AuthenticationException('Unknown user.');
            }

            return $user;
        }));
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        return null;
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): ?Response
    {
        return new Response('', Response::HTTP_UNAUTHORIZED);
    }
}
