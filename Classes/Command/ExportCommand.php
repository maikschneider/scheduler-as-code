<?php

declare(strict_types=1);

namespace MaikSchneider\SchedulerAsCode\Command;

use MaikSchneider\SchedulerAsCode\Configuration\TaskDefinitionProvider;
use MaikSchneider\SchedulerAsCode\Persistence\ManagedTaskRepository;
use MaikSchneider\SchedulerAsCode\Persistence\UnknownTaskTypeException;
use MaikSchneider\SchedulerAsCode\Service\TaskExporter;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'scheduler:export',
    description: 'Export scheduler tasks from the database into config/scheduler/*.yaml.',
)]
final class ExportCommand extends Command
{
    public function __construct(
        private readonly ManagedTaskRepository $managedTaskRepository,
        private readonly TaskExporter $exporter,
        private readonly TaskDefinitionProvider $definitionProvider,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setHelp(
                'Without arguments, every task that is not linked to a file yet is exported.' . LF
                . 'Exported tasks are linked to their file and from then on imported from it.'
            )
            ->addArgument('uid', InputArgument::IS_ARRAY | InputArgument::OPTIONAL, 'Uids of the tasks to export')
            ->addOption('identifier', 'i', InputOption::VALUE_REQUIRED, 'File name (without .yaml) for a single exported task')
            ->addOption('force', 'f', InputOption::VALUE_NONE, 'Also export tasks that are already linked, overwriting their files');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $uids = array_values(array_map(intval(...), (array)$input->getArgument('uid')));
        $identifier = $input->getOption('identifier');
        $force = (bool)$input->getOption('force');

        if ($identifier !== null && count($uids) !== 1) {
            $io->error('--identifier needs exactly one task uid.');
            return Command::INVALID;
        }
        if ($identifier !== null && preg_match('/^[a-z0-9][a-z0-9_-]*$/', (string)$identifier) !== 1) {
            $io->error('--identifier may only contain lowercase letters, digits, "-" and "_".');
            return Command::INVALID;
        }

        $rows = $this->managedTaskRepository->findTasks($uids);
        if ($uids !== [] && count($rows) !== count($uids)) {
            $found = array_map(static fn (array $row): int => (int)$row['uid'], $rows);
            $io->error(sprintf('No task with uid %s.', implode(', ', array_diff($uids, $found))));
            return Command::FAILURE;
        }

        $exported = 0;
        $result = Command::SUCCESS;
        foreach ($rows as $row) {
            $linkedTo = (string)($row['tx_schedulerascode_identifier'] ?? '');
            if ($linkedTo !== '' && !$force) {
                $io->writeln(sprintf('Task %d is already linked to "%s", skipped. Use --force to overwrite the file.', (int)$row['uid'], $linkedTo), OutputInterface::VERBOSITY_VERBOSE);
                continue;
            }
            try {
                $file = $this->exporter->export($row, is_string($identifier) ? $identifier : null);
            } catch (UnknownTaskTypeException $e) {
                $io->warning($e->getMessage());
                $result = Command::FAILURE;
                continue;
            }
            $io->writeln(sprintf('Task %d → %s', (int)$row['uid'], $file));
            $exported++;
        }

        if ($exported === 0 && $result === Command::SUCCESS) {
            $io->note('Nothing to export. Tasks already linked to a file are only exported with --force.');
            return Command::SUCCESS;
        }
        $io->success(sprintf('Exported %d task(s) to %s. Commit them to version control.', $exported, $this->definitionProvider->getDirectory()));
        return $result;
    }
}
