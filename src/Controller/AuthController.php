<?php

namespace App\Controller;

use App\Entity\LoginChallenge;
use App\Mailer\TransactionalMailer;
use App\Security\LoginChallengeManager;
use App\Security\OtpLoginAuthenticator;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class AuthController extends AbstractController
{
    public function __construct(
        private readonly LoginChallengeManager $loginChallengeManager,
        private readonly TransactionalMailer $transactionalMailer,
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly RateLimiterFactory $authRequestLimiter,
        private readonly RateLimiterFactory $authVerifyLimiter,
        private readonly Security $security,
    ) {
    }

    #[Route('/connexion', name: 'app_login_email', methods: ['POST'])]
    public function requestLoginLink(Request $request): RedirectResponse
    {
        $email = trim($request->request->getString('email'));
        $purpose = $request->request->getString('purpose', LoginChallenge::PURPOSE_LOGIN);

        if ('' === $email || false === filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->addFlash('error', 'Merci de renseigner une adresse email valide.');

            return $this->redirectToRoute('app_home', ['email' => $email]);
        }

        $limiter = $this->authRequestLimiter->create(sprintf('request:%s:%s', mb_strtolower($email), $request->getClientIp() ?? 'ipless'));

        if (!$limiter->consume()->isAccepted()) {
            $this->addFlash('error', 'Trop de demandes de connexion. Reessayez dans quelques minutes.');

            return $this->redirectToRoute('app_home', ['email' => $email]);
        }

        $user = $this->loginChallengeManager->getOrCreateUser($email);
        $createdChallenge = $this->loginChallengeManager->issueChallenge($user, $purpose);
        $magicLink = $this->urlGenerator->generate('app_auth_magic', [
            'selector' => $createdChallenge->challenge->getSelector(),
            'token' => $createdChallenge->plainToken,
        ], UrlGeneratorInterface::ABSOLUTE_URL);

        $this->transactionalMailer->sendLoginChallenge($user, $createdChallenge->challenge, $magicLink);

        $this->addFlash('success', sprintf('Un email de connexion a ete envoye a %s.', $user->getEmail()));

        return $this->redirectToRoute('app_auth_verify', [
            'email' => $user->getEmail(),
            'purpose' => $purpose,
        ]);
    }

    #[Route('/connexion/verifier', name: 'app_auth_verify', methods: ['GET', 'POST'])]
    public function verify(Request $request): Response
    {
        if ('POST' === $request->getMethod()) {
            $email = $request->request->getString('email');
            $purpose = $request->request->getString('purpose', LoginChallenge::PURPOSE_LOGIN);
            $limiter = $this->authVerifyLimiter->create(sprintf('verify:%s:%s', mb_strtolower(trim($email)), $request->getClientIp() ?? 'ipless'));

            if (!$limiter->consume()->isAccepted()) {
                $this->addFlash('error', 'Trop de tentatives. Reessayez dans quelques minutes.');

                return $this->redirectToRoute('app_auth_verify', ['email' => $email, 'purpose' => $purpose]);
            }

            $challenge = $this->loginChallengeManager->consumeByCode($email, $request->request->getString('code'), $purpose);

            if (null === $challenge) {
                $this->addFlash('error', 'Code OTP invalide ou expire.');

                return $this->redirectToRoute('app_auth_verify', ['email' => $email, 'purpose' => $purpose]);
            }

            $this->security->login($challenge->getUser(), OtpLoginAuthenticator::class, 'main');

            if (!$challenge->getUser()->hasPin() || LoginChallenge::PURPOSE_RESET_PIN === $challenge->getPurpose()) {
                return $this->redirectToRoute('app_pin_setup');
            }

            return $this->redirectToRoute('app_pin_unlock');
        }

        return $this->render('auth/verify.html.twig', [
            'email' => $request->query->getString('email'),
            'purpose' => $request->query->getString('purpose', LoginChallenge::PURPOSE_LOGIN),
        ]);
    }

    #[Route('/connexion/magic/{selector}/{token}', name: 'app_auth_magic', methods: ['GET'])]
    public function magicLink(string $selector, string $token): RedirectResponse
    {
        $challenge = $this->loginChallengeManager->consumeByMagicLink($selector, $token);

        if (null === $challenge) {
            $this->addFlash('error', 'Lien magique invalide ou expire.');

            return $this->redirectToRoute('app_home');
        }

        $this->security->login($challenge->getUser(), OtpLoginAuthenticator::class, 'main');

        if (!$challenge->getUser()->hasPin() || LoginChallenge::PURPOSE_RESET_PIN === $challenge->getPurpose()) {
            return $this->redirectToRoute('app_pin_setup');
        }

        return $this->redirectToRoute('app_pin_unlock');
    }

    #[Route('/logout', name: 'app_logout', methods: ['POST'])]
    public function logout(): never
    {
        throw new \LogicException('Handled by Symfony security.');
    }
}
