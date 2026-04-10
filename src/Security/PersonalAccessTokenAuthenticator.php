<?php

namespace App\Security;

use App\Entity\User;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\Authenticator\AbstractAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;

final class PersonalAccessTokenAuthenticator extends AbstractAuthenticator
{
    public function __construct(
        private readonly PersonalAccessTokenService $personalAccessTokenService,
        private readonly string $publicApiHost,
    ) {
    }

    public function supports(Request $request): ?bool
    {
        return strtolower($this->publicApiHost) === strtolower($request->getHost())
            && $request->headers->has('Authorization');
    }

    public function authenticate(Request $request): SelfValidatingPassport
    {
        $authorization = (string) $request->headers->get('Authorization');

        if (!str_starts_with($authorization, 'Bearer ')) {
            throw new AuthenticationException('Missing bearer token.');
        }

        $tokenValue = substr($authorization, 7);
        $token = $this->personalAccessTokenService->findActiveByPlainText($tokenValue);

        if (null === $token) {
            throw new AuthenticationException('Invalid personal access token.');
        }

        $user = $token->getUser();

        if (!$user instanceof User || $user->isDeleted()) {
            throw new AuthenticationException('Unknown user.');
        }

        $this->personalAccessTokenService->markUsed($token);

        return new SelfValidatingPassport(
            new UserBadge((string) $user->getId(), static fn (string $userId) => $user)
        );
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
