<?php

namespace App\Command;

use App\Workspace\ExecutiveCommitteeFixtureService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'toastit:workspace:seed-executive-committee', description: 'Generate six months of executive committee fixtures for a target workspace.')]
final class SeedExecutiveCommitteeFixtureCommand extends Command
{
    public function __construct(
        private readonly ExecutiveCommitteeFixtureService $fixtureService,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('workspaceId', InputArgument::OPTIONAL, 'Workspace id to seed.', 2);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $workspaceId = (int) $input->getArgument('workspaceId');
        $counts = $this->fixtureService->seed($workspaceId);

        $io->success(sprintf('Workspace %d seeded with Braincube ExCo fixtures.', $workspaceId));
        $io->table(['Members', 'Items', 'Comments', 'Votes', 'Sessions'], [[
            $counts['members'],
            $counts['items'],
            $counts['comments'],
            $counts['votes'],
            $counts['sessions'],
        ]]);

        return Command::SUCCESS;
    }
}
