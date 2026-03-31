<?php

namespace App\Controller\App\Dashboard;

use App\Entity\Team;
use App\Entity\TeamMember;
use App\Api\DashboardPayloadBuilder;
use App\Security\JwtTokenManager;
use App\Repository\MeetingRepository;
use App\Repository\TeamRepository;
use App\Workspace\WorkspaceAccess;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class IndexController extends AbstractController
{
    public function __construct(
        private readonly WorkspaceAccess $workspaceAccess,
        private readonly TeamRepository $teamRepository,
        private readonly MeetingRepository $meetingRepository,
        private readonly DashboardPayloadBuilder $dashboardPayloadBuilder,
        private readonly JwtTokenManager $jwtTokenManager,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    #[Route('/app', name: 'app_dashboard', methods: ['POST'])]
    public function __invoke(Request $request): Response
    {
        $user = $this->workspaceAccess->getUserOrFail();

        if ('POST' === $request->getMethod()) {
            $name = trim($request->request->getString('name'));

            if ('' === $name) {
                $this->addFlash('error', 'Le nom de l\'equipe est requis.');

                return $this->redirectToRoute('app_dashboard');
            }

            $team = (new Team())
                ->setName($name)
                ->setOrganizer($user);

            $membership = (new TeamMember())
                ->setUser($user);

            $team->addMembership($membership);
            $this->entityManager->persist($team);
            $this->entityManager->persist($membership);
            $this->entityManager->flush();

            return $this->redirectToRoute('app_team_show', ['id' => $team->getId()]);
        }

        throw new \LogicException('GET handled by SPA shell.');
    }
}
