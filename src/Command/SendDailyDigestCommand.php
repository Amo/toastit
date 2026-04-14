<?php

namespace App\Command;

use App\Repository\UserRepository;
use App\Workspace\TodoDigestService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:digest:daily',
    description: 'Send daily collaboration recap emails for yesterday (or a specific date).',
)]
final class SendDailyDigestCommand extends Command
{
    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly TodoDigestService $todoDigestService,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('email', null, InputOption::VALUE_REQUIRED, 'Only send digest to this email')
            ->addOption('date', null, InputOption::VALUE_REQUIRED, 'Target day in YYYY-MM-DD format (defaults to yesterday)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $targetDate = $this->resolveTargetDate($input->getOption('date'));
        if (null === $targetDate) {
            $io->error('Invalid --date format. Expected YYYY-MM-DD.');

            return Command::INVALID;
        }

        $email = trim((string) $input->getOption('email'));
        $recipients = '' !== $email
            ? array_filter(
                [$this->userRepository->findOneByNormalizedEmail(mb_strtolower($email))],
                fn ($user): bool => null !== $user && $this->userRepository->isEligibleForDailyDigest($user)
            )
            : $this->userRepository->findDigestRecipients();

        if ([] === $recipients) {
            $io->warning('No recipients found.');

            return Command::SUCCESS;
        }

        $sent = 0;
        foreach ($recipients as $recipient) {
            $this->todoDigestService->sendDailyCollaborationRecap($recipient, $targetDate);
            ++$sent;
        }

        $io->success(sprintf(
            'Daily collaboration recap sent to %d user%s for %s.',
            $sent,
            $sent > 1 ? 's' : '',
            $targetDate->format('Y-m-d')
        ));

        return Command::SUCCESS;
    }

    private function resolveTargetDate(mixed $value): ?\DateTimeImmutable
    {
        if (!is_string($value) || '' === trim($value)) {
            return new \DateTimeImmutable('yesterday');
        }

        $date = \DateTimeImmutable::createFromFormat('Y-m-d', trim($value));
        if (!$date instanceof \DateTimeImmutable) {
            return null;
        }

        return $date;
    }
}
