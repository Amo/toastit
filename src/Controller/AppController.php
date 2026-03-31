<?php

namespace App\Controller;

use App\Entity\Meeting;
use App\Entity\MeetingAttendee;
use App\Entity\ParkingLotItem;
use App\Entity\Team;
use App\Entity\TeamMember;
use App\Entity\User;
use App\Entity\Vote;
use App\Repository\MeetingRepository;
use App\Repository\TeamRepository;
use App\Repository\VoteRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use App\Security\EmailNormalizer;

final class AppController extends AbstractController
{
    public function __construct(
        private readonly TeamRepository $teamRepository,
        private readonly MeetingRepository $meetingRepository,
        private readonly VoteRepository $voteRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly Security $security,
        private readonly EmailNormalizer $emailNormalizer,
    ) {
    }

    #[Route('/app', name: 'app_dashboard', methods: ['GET', 'POST'])]
    public function dashboard(Request $request): Response
    {
        $user = $this->getUserOrFail();

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

        return $this->render('app/dashboard.html.twig', [
            'teams' => $this->teamRepository->findForUser($user),
            'ad_hoc_meetings' => $this->meetingRepository->findAdHocForUser($user),
        ]);
    }

    #[Route('/app/teams/{id}', name: 'app_team_show', methods: ['GET'])]
    public function team(int $id): Response
    {
        $team = $this->getTeamOrFail($id);

        return $this->render('app/team.html.twig', [
            'team' => $team,
        ]);
    }

    #[Route('/app/teams/{id}/invite', name: 'app_team_invite', methods: ['POST'])]
    public function inviteTeamMember(int $id, Request $request): RedirectResponse
    {
        $team = $this->getTeamOrFail($id);
        $email = trim($request->request->getString('email'));

        if ('' === $email || false === filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->addFlash('error', 'Merci de renseigner une adresse email valide.');

            return $this->redirectToRoute('app_team_show', ['id' => $team->getId()]);
        }

        $user = $this->findOrCreateUserByEmail($email);

        foreach ($team->getMemberships() as $membership) {
            if ($membership->getUser()->getId() === $user->getId()) {
                $this->addFlash('success', sprintf('%s fait deja partie de l equipe.', $user->getDisplayName()));

                return $this->redirectToRoute('app_team_show', ['id' => $team->getId()]);
            }
        }

        $membership = (new TeamMember())
            ->setTeam($team)
            ->setUser($user);

        $this->entityManager->persist($membership);
        $this->entityManager->flush();
        $this->addFlash('success', sprintf('%s a ete ajoute a l equipe.', $user->getDisplayName()));

        return $this->redirectToRoute('app_team_show', ['id' => $team->getId()]);
    }

    #[Route('/app/meetings/{id}', name: 'app_meeting_show', methods: ['GET'])]
    public function meeting(int $id): Response
    {
        $meeting = $this->getMeetingOrFail($id);

        return $this->render('app/meeting.html.twig', [
            'meeting' => $meeting,
            'team' => $meeting->getTeam(),
            'current_user_id' => $this->getUserOrFail()->getId(),
        ]);
    }

    #[Route('/app/meetings/{id}/invite', name: 'app_meeting_invite', methods: ['POST'])]
    public function inviteMeetingAttendee(int $id, Request $request): RedirectResponse
    {
        $meeting = $this->getMeetingOrFail($id);
        $email = trim($request->request->getString('email'));

        if ('' === $email || false === filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->addFlash('error', 'Merci de renseigner une adresse email valide.');

            return $this->redirectToRoute('app_meeting_show', ['id' => $meeting->getId()]);
        }

        $user = $this->findOrCreateUserByEmail($email);

        if ($meeting->getOrganizer()->getId() === $user->getId()) {
            $this->addFlash('success', sprintf('%s est deja organisateur de ce meeting.', $user->getDisplayName()));

            return $this->redirectToRoute('app_meeting_show', ['id' => $meeting->getId()]);
        }

        foreach ($meeting->getAttendees() as $attendee) {
            if ($attendee->getUser()->getId() === $user->getId()) {
                $this->addFlash('success', sprintf('%s est deja invite a ce meeting.', $user->getDisplayName()));

                return $this->redirectToRoute('app_meeting_show', ['id' => $meeting->getId()]);
            }
        }

        $attendee = (new MeetingAttendee())
            ->setMeeting($meeting)
            ->setUser($user);

        $this->entityManager->persist($attendee);
        $this->entityManager->flush();
        $this->addFlash('success', sprintf('%s a ete invite au meeting.', $user->getDisplayName()));

        return $this->redirectToRoute('app_meeting_show', ['id' => $meeting->getId()]);
    }

    #[Route('/app/teams/{id}/meetings', name: 'app_team_meeting_create', methods: ['POST'])]
    public function createMeeting(int $id, Request $request): RedirectResponse
    {
        $team = $this->getTeamOrFail($id);
        $title = trim($request->request->getString('title'));
        $scheduledAtRaw = $request->request->getString('scheduled_at');

        if ('' === $title || '' === $scheduledAtRaw) {
            $this->addFlash('error', 'Le titre et la date du meeting sont requis.');

            return $this->redirectToRoute('app_team_show', ['id' => $team->getId()]);
        }

        try {
            $scheduledAt = new \DateTimeImmutable($scheduledAtRaw);
        } catch (\Exception) {
            $this->addFlash('error', 'La date du meeting est invalide.');

            return $this->redirectToRoute('app_team_show', ['id' => $team->getId()]);
        }

        $meeting = (new Meeting())
            ->setTeam($team)
            ->setOrganizer($this->getUserOrFail())
            ->setTitle($title)
            ->setScheduledAt($scheduledAt)
            ->setIsRecurring($request->request->getBoolean('is_recurring'))
            ->setRecurrence($this->buildRecurrenceLabel(
                $request->request->getBoolean('is_recurring'),
                $request->request->getString('recurrence_quantity'),
                $request->request->getString('recurrence_unit')
            ))
            ->setVideoLink($request->request->getString('video_link') ?: null);

        $this->entityManager->persist($meeting);
        $this->entityManager->flush();

        return $this->redirectToRoute('app_team_show', ['id' => $team->getId()]);
    }

    #[Route('/app/meetings/ad-hoc', name: 'app_ad_hoc_meeting_create', methods: ['POST'])]
    public function createAdHocMeeting(Request $request): RedirectResponse
    {
        $title = trim($request->request->getString('title'));
        $scheduledAtRaw = $request->request->getString('scheduled_at');

        if ('' === $title || '' === $scheduledAtRaw) {
            $this->addFlash('error', 'Le titre et la date du meeting sont requis.');

            return $this->redirectToRoute('app_dashboard');
        }

        try {
            $scheduledAt = new \DateTimeImmutable($scheduledAtRaw);
        } catch (\Exception) {
            $this->addFlash('error', 'La date du meeting est invalide.');

            return $this->redirectToRoute('app_dashboard');
        }

        $meeting = (new Meeting())
            ->setOrganizer($this->getUserOrFail())
            ->setTitle($title)
            ->setScheduledAt($scheduledAt)
            ->setIsRecurring($request->request->getBoolean('is_recurring'))
            ->setRecurrence($this->buildRecurrenceLabel(
                $request->request->getBoolean('is_recurring'),
                $request->request->getString('recurrence_quantity'),
                $request->request->getString('recurrence_unit')
            ))
            ->setVideoLink($request->request->getString('video_link') ?: null);

        $this->entityManager->persist($meeting);
        $this->entityManager->flush();

        return $this->redirectToRoute('app_meeting_show', ['id' => $meeting->getId()]);
    }

    #[Route('/app/meetings/{id}/items', name: 'app_meeting_item_create', methods: ['POST'])]
    public function createItem(int $id, Request $request): RedirectResponse
    {
        $meeting = $this->getMeetingOrFail($id);
        $team = $meeting->getTeam();
        $title = trim($request->request->getString('title'));

        if ('' === $title) {
            $this->addFlash('error', 'Le titre du sujet est requis.');

            return $this->redirectToRoute('app_meeting_show', ['id' => $meeting->getId()]);
        }

        $item = (new ParkingLotItem())
            ->setTeam($team)
            ->setMeeting($meeting)
            ->setAuthor($this->getUserOrFail())
            ->setTitle($title)
            ->setDescription(trim($request->request->getString('description')) ?: null);

        $this->entityManager->persist($item);
        $this->entityManager->flush();

        return $this->redirectToRoute('app_meeting_show', ['id' => $meeting->getId()]);
    }

    #[Route('/app/items/{id}/vote', name: 'app_item_vote_toggle', methods: ['POST'])]
    public function toggleVote(int $id, Request $request): Response
    {
        $user = $this->getUserOrFail();
        $item = $this->entityManager->getRepository(ParkingLotItem::class)->find($id);

        if (!$item instanceof ParkingLotItem) {
            throw $this->createNotFoundException();
        }

        $existingVote = $this->voteRepository->findOneForItemAndUser($item, $user);
        $voted = true;

        if ($existingVote instanceof Vote) {
            $this->entityManager->remove($existingVote);
            $voted = false;
        } else {
            $vote = (new Vote())
                ->setItem($item)
                ->setUser($user);

            $this->entityManager->persist($vote);
        }

        $this->entityManager->flush();

        if ($request->isXmlHttpRequest() || str_contains((string) $request->headers->get('Accept'), 'application/json')) {
            return new JsonResponse([
                'id' => $item->getId(),
                'voted' => $voted,
                'voteCount' => $item->getVoteCount(),
            ]);
        }

        return $this->redirectToRoute('app_meeting_show', ['id' => $item->getMeeting()->getId()]);
    }

    #[Route('/app/items/{id}/relocate', name: 'app_item_relocate', methods: ['POST'])]
    public function relocateItem(int $id, Request $request): RedirectResponse
    {
        $item = $this->entityManager->getRepository(ParkingLotItem::class)->find($id);

        if (!$item instanceof ParkingLotItem) {
            throw $this->createNotFoundException();
        }

        $currentMeeting = $this->getMeetingOrFail($item->getMeeting()->getId());
        $targetMeetingId = $request->request->getInt('target_meeting_id');
        $mode = $request->request->getString('mode');

        $targetMeeting = $this->entityManager->getRepository(Meeting::class)->find($targetMeetingId);

        if (!$targetMeeting instanceof Meeting || $targetMeeting->getTeam()->getId() !== $currentMeeting->getTeam()->getId()) {
            $this->addFlash('error', 'Le meeting cible est invalide.');

            return $this->redirectToRoute('app_meeting_show', ['id' => $currentMeeting->getId()]);
        }

        if ($targetMeeting->getId() === $currentMeeting->getId()) {
            $this->addFlash('error', 'Choisissez un autre meeting cible.');

            return $this->redirectToRoute('app_meeting_show', ['id' => $currentMeeting->getId()]);
        }

        if ('copy' === $mode) {
            $copy = (new ParkingLotItem())
                ->setTeam($item->getTeam())
                ->setMeeting($targetMeeting)
                ->setAuthor($item->getAuthor())
                ->setTitle($item->getTitle())
                ->setDescription($item->getDescription())
                ->setStatus($item->getStatus());

            $this->entityManager->persist($copy);
            $this->entityManager->flush();
            $this->addFlash('success', sprintf('Le sujet "%s" a ete copie vers "%s".', $item->getTitle(), $targetMeeting->getTitle()));

            return $this->redirectToRoute('app_meeting_show', ['id' => $targetMeeting->getId()]);
        }

        if ('move' !== $mode) {
            $this->addFlash('error', 'Action de deplacement invalide.');

            return $this->redirectToRoute('app_meeting_show', ['id' => $currentMeeting->getId()]);
        }

        $item->setMeeting($targetMeeting);
        $this->entityManager->flush();
        $this->addFlash('success', sprintf('Le sujet "%s" a ete transfere vers "%s".', $item->getTitle(), $targetMeeting->getTitle()));

        return $this->redirectToRoute('app_meeting_show', ['id' => $targetMeeting->getId()]);
    }

    #[Route('/app/items/{id}/delete', name: 'app_item_delete', methods: ['POST'])]
    public function deleteItem(int $id): RedirectResponse
    {
        $item = $this->entityManager->getRepository(ParkingLotItem::class)->find($id);

        if (!$item instanceof ParkingLotItem) {
            throw $this->createNotFoundException();
        }

        $meeting = $this->getMeetingOrFail($item->getMeeting()->getId());
        $user = $this->getUserOrFail();

        if ($item->getAuthor()->getId() !== $user->getId()) {
            $this->addFlash('error', 'Seul l auteur du sujet peut le supprimer.');

            return $this->redirectToRoute('app_meeting_show', ['id' => $meeting->getId()]);
        }

        $this->entityManager->remove($item);
        $this->entityManager->flush();
        $this->addFlash('success', 'Le sujet a ete supprime.');

        return $this->redirectToRoute('app_meeting_show', ['id' => $meeting->getId()]);
    }

    private function getUserOrFail(): User
    {
        $user = $this->security->getUser();

        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }

        return $user;
    }

    private function getTeamOrFail(int $teamId): Team
    {
        $team = $this->teamRepository->findOneForUser($teamId, $this->getUserOrFail());

        if (!$team instanceof Team) {
            throw $this->createNotFoundException();
        }

        return $team;
    }

    private function getMeetingOrFail(int $meetingId): Meeting
    {
        $meeting = $this->entityManager->getRepository(Meeting::class)->find($meetingId);

        if (!$meeting instanceof Meeting) {
            throw $this->createNotFoundException();
        }

        if (null !== $meeting->getTeam()) {
            $team = $this->getTeamOrFail($meeting->getTeam()->getId());

            if ($meeting->getTeam()->getId() !== $team->getId()) {
                throw $this->createNotFoundException();
            }

            return $meeting;
        }

        $user = $this->getUserOrFail();

        if ($meeting->getOrganizer()->getId() === $user->getId()) {
            return $meeting;
        }

        foreach ($meeting->getAttendees() as $attendee) {
            if ($attendee->getUser()->getId() === $user->getId()) {
                return $meeting;
            }
        }

        if ($meeting->getOrganizer()->getId() !== $user->getId()) {
            throw $this->createNotFoundException();
        }

        return $meeting;
    }

    private function findOrCreateUserByEmail(string $email): User
    {
        $normalizedEmail = $this->emailNormalizer->normalize($email);
        $user = $this->entityManager->getRepository(User::class)->findOneBy(['email' => $normalizedEmail]);

        if ($user instanceof User) {
            return $user;
        }

        $user = (new User())->setEmail($normalizedEmail);
        $this->entityManager->persist($user);
        $this->entityManager->flush();

        return $user;
    }

    private function buildRecurrenceLabel(bool $isRecurring, string $quantity, string $unit): ?string
    {
        if (!$isRecurring) {
            return null;
        }

        $quantity = trim($quantity);
        $unit = trim($unit);

        if (!ctype_digit($quantity) || (int) $quantity < 1) {
            return null;
        }

        $allowedUnits = [
            'days' => 'jour(s)',
            'week' => 'semaine',
            'two_weeks' => 'deux semaines',
            'months' => 'mois',
            'two_months' => 'deux mois',
            'quarter' => 'trimestre',
            'semester' => 'semestre',
        ];

        if (!array_key_exists($unit, $allowedUnits)) {
            return null;
        }

        return sprintf('%s × %s', $quantity, $allowedUnits[$unit]);
    }
}
