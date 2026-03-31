<?php

namespace App\Controller;

use App\Security\JwtTokenManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class ProfileController extends AbstractController
{
    public function __construct(
        private readonly Security $security,
        private readonly EntityManagerInterface $entityManager,
        private readonly JwtTokenManager $jwtTokenManager,
    ) {
    }

    #[Route('/app/profile', name: 'app_profile', methods: ['POST'])]
    public function profile(Request $request): Response
    {
        $user = $this->getUserOrFail();

        if ('POST' === $request->getMethod()) {
            $user
                ->setFirstName($request->request->getString('first_name') ?: null)
                ->setLastName($request->request->getString('last_name') ?: null);

            $this->entityManager->flush();
            $this->addFlash('success', 'Votre profil a ete mis a jour.');

            return $this->redirectToRoute('app_profile');
        }

        throw new \LogicException('GET handled by SPA shell.');
    }

    private function getUserOrFail(): \App\Entity\User
    {
        $user = $this->security->getUser();

        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }

        return $user;
    }
}
