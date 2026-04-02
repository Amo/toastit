<?php

namespace App\Tests\Unit;

use App\Entity\User;
use App\Repository\UserRepository;
use App\Security\ApiTokenAuthenticator;
use App\Security\JwtTokenService;
use App\Tests\Support\ReflectionHelper;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;

final class ApiTokenAuthenticatorTest extends TestCase
{
    public function testSupportsOnlyProtectedApiRequestsWithAuthorizationHeader(): void
    {
        $authenticator = new ApiTokenAuthenticator(
            new JwtTokenService('test-secret'),
            $this->createMock(UserRepository::class),
        );

        $request = Request::create('/api/dashboard', 'GET', server: ['HTTP_AUTHORIZATION' => 'Bearer token']);
        self::assertTrue($authenticator->supports($request));
        self::assertFalse($authenticator->supports(Request::create('/api/auth/request-otp', 'POST', server: ['HTTP_AUTHORIZATION' => 'Bearer token'])));
        self::assertFalse($authenticator->supports(Request::create('/app', 'GET', server: ['HTTP_AUTHORIZATION' => 'Bearer token'])));
        self::assertFalse($authenticator->supports(Request::create('/api/dashboard', 'GET')));
    }

    public function testAuthenticateRejectsInvalidBearerTokenFormatAndPayload(): void
    {
        $user = (new User())->setEmail('user@example.com');
        ReflectionHelper::setId($user, 99);
        $jwtTokenManager = new JwtTokenService('test-secret');
        $repository = $this->createMock(UserRepository::class);
        $authenticator = new ApiTokenAuthenticator($jwtTokenManager, $repository);

        try {
            $authenticator->authenticate(Request::create('/api/dashboard', 'GET', server: ['HTTP_AUTHORIZATION' => 'Token nope']));
            self::fail('Expected authentication exception.');
        } catch (AuthenticationException) {
            self::assertTrue(true);
        }

        $this->expectException(AuthenticationException::class);
        $authenticator->authenticate(Request::create('/api/dashboard', 'GET', server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$jwtTokenManager->createPinSetupToken($user, new \DateTimeImmutable()),
        ]));
    }

    public function testAuthenticateLoadsKnownUserAndFailureReturnsUnauthorized(): void
    {
        $user = (new User())->setEmail('user@example.com');
        ReflectionHelper::setId($user, 15);
        $jwtTokenManager = new JwtTokenService('test-secret');

        $repository = $this->createMock(UserRepository::class);
        $repository
            ->expects(self::once())
            ->method('find')
            ->with(15)
            ->willReturn($user);

        $authenticator = new ApiTokenAuthenticator($jwtTokenManager, $repository);
        $passport = $authenticator->authenticate(Request::create('/api/dashboard', 'GET', server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$jwtTokenManager->createAccessToken($user, new \DateTimeImmutable()),
        ]));
        $badge = $passport->getBadge(UserBadge::class);

        self::assertInstanceOf(UserBadge::class, $badge);
        self::assertSame($user, $badge->getUser());
        self::assertNull($authenticator->onAuthenticationSuccess(Request::create('/api/dashboard'), $this->createMock(\Symfony\Component\Security\Core\Authentication\Token\TokenInterface::class), 'api'));
        self::assertSame(401, $authenticator->onAuthenticationFailure(Request::create('/api/dashboard'), new AuthenticationException())->getStatusCode());
    }
}
