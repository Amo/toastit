<?php

namespace App\Controller;

use App\Entity\LoginChallenge;
use App\Entity\User;
use App\Mailer\TransactionalMailer;
use App\Security\LoginChallengeManager;
use App\Security\PinManager;
use App\Security\PinSessionManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class PinController extends AbstractController
{
    public function __construct(
        private readonly PinManager $pinManager,
        private readonly PinSessionManager $pinSessionManager,
        private readonly EntityManagerInterface $entityManager,
        private readonly Security $security,
        private readonly LoginChallengeManager $loginChallengeManager,
        private readonly TransactionalMailer $transactionalMailer,
        private readonly UrlGeneratorInterface $urlGenerator,
    ) {
    }

    #[Route('/pin/setup', name: 'app_pin_setup', methods: ['POST'])]
    public function setup(Request $request): Response
    {
        $user = $this->getAuthenticatedUser();

        if ('POST' === $request->getMethod()) {
            $pin = $request->request->getString('pin');
            $pinConfirmation = $request->request->getString('pin_confirmation');

            if (!preg_match('/^\d{4}$/', $pin)) {
                $this->addFlash('error', 'Le PIN doit contenir exactement 4 chiffres.');

                return $this->redirectToRoute('app_pin_setup');
            }

            if ($pin !== $pinConfirmation) {
                $this->addFlash('error', 'La confirmation du PIN ne correspond pas.');

                return $this->redirectToRoute('app_pin_setup');
            }

            $user->setPinHash($this->pinManager->hashPin($user, $pin));
            $user->setPinSetAt(new \DateTimeImmutable());
            $this->entityManager->flush();
            $this->pinSessionManager->markVerified();

            return $this->redirectToRoute('app_dashboard');
        }

        throw new \LogicException('GET handled by SPA shell.');
    }

    #[Route('/pin/unlock', name: 'app_pin_unlock', methods: ['POST'])]
    public function unlock(Request $request): Response
    {
        $user = $this->getAuthenticatedUser();

        if ('POST' === $request->getMethod()) {
            if (!$this->pinManager->verifyPin($user, $request->request->getString('pin'))) {
                $this->addFlash('error', 'PIN invalide.');

                return $this->redirectToRoute('app_pin_unlock');
            }

            $this->pinSessionManager->markVerified();

            return $this->redirectToRoute('app_dashboard');
        }

        throw new \LogicException('GET handled by SPA shell.');
    }

    #[Route('/pin/forgot', name: 'app_pin_forgot', methods: ['POST'])]
    public function forgotPin(): RedirectResponse
    {
        $user = $this->getAuthenticatedUser();
        $createdChallenge = $this->loginChallengeManager->issueChallenge($user, LoginChallenge::PURPOSE_RESET_PIN);
        $magicLink = $this->urlGenerator->generate('app_auth_magic', [
            'selector' => $createdChallenge->challenge->getSelector(),
            'token' => $createdChallenge->plainToken,
        ], UrlGeneratorInterface::ABSOLUTE_URL);

        $this->transactionalMailer->sendLoginChallenge($user, $createdChallenge->challenge, $magicLink);
        $this->pinSessionManager->clear();
        $this->addFlash('success', sprintf('Un email de reinitialisation du PIN a ete envoye a %s.', $user->getEmail()));

        return $this->redirectToRoute('app_auth_verify', [
            'email' => $user->getEmail(),
            'purpose' => LoginChallenge::PURPOSE_RESET_PIN,
        ]);
    }

    private function getAuthenticatedUser(): User
    {
        $user = $this->security->getUser();

        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }

        return $user;
    }
}
