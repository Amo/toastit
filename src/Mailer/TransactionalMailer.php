<?php

namespace App\Mailer;

use App\Entity\LoginChallenge;
use App\Entity\User;
use Twig\Environment;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;

final class TransactionalMailer
{
    public function __construct(
        private readonly MailerInterface $mailer,
        private readonly Environment $twig,
        private readonly string $defaultFrom,
    ) {
    }

    public function sendLoginChallenge(User $user, LoginChallenge $challenge, string $magicLink): void
    {
        $context = [
            'user' => $user,
            'challenge' => $challenge,
            'magic_link' => $magicLink,
        ];

        $email = (new Email())
            ->from(new Address($this->defaultFrom, 'Toastit'))
            ->to($user->getEmail())
            ->subject('Votre code de connexion Toastit')
            ->html($this->twig->render('emails/auth/login_challenge.html.twig', $context))
            ->text($this->twig->render('emails/auth/login_challenge.txt.twig', $context));

        $this->mailer->send($email);
    }

    public function sendDeleteAccountChallenge(User $user, LoginChallenge $challenge): void
    {
        $context = [
            'user' => $user,
            'challenge' => $challenge,
        ];

        $email = (new Email())
            ->from(new Address($this->defaultFrom, 'Toastit'))
            ->to($user->getEmail())
            ->subject('Votre code de suppression de compte Toastit')
            ->html($this->twig->render('emails/auth/delete_account_challenge.html.twig', $context))
            ->text($this->twig->render('emails/auth/delete_account_challenge.txt.twig', $context));

        $this->mailer->send($email);
    }
}
