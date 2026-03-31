<?php

namespace App\Command;

use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'toastit:user:root', description: 'Grant ROLE_ROOT to an existing user by email.')]
final class GrantRootUserCommand extends Command
{
    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly EntityManagerInterface $entityManager,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('email', InputArgument::REQUIRED, 'Normalized email of the user to promote.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $email = mb_strtolower(trim((string) $input->getArgument('email')));
        $user = $this->userRepository->findOneByNormalizedEmail($email);

        if (null === $user) {
            $io->error(sprintf('No user found for "%s".', $email));

            return Command::FAILURE;
        }

        if ($user->isRoot()) {
            $io->warning(sprintf('User "%s" already has ROLE_ROOT.', $email));

            return Command::SUCCESS;
        }

        $user->addRole('ROLE_ROOT');
        $this->entityManager->flush();

        $io->success(sprintf('User "%s" is now a ROOT user.', $email));

        return Command::SUCCESS;
    }
}
