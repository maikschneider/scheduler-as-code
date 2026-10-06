<?php

declare(strict_types=1);

namespace MaikSchneider\SchedulerAsCode\Tests\Functional;

use MaikSchneider\SchedulerAsCode\Configuration\TaskDefinitionProvider;
use MaikSchneider\SchedulerAsCode\Service\TaskSynchronizer;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Tester\CommandTester;
use TYPO3\CMS\Core\Console\CommandRegistry;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Information\Typo3Version;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

final class ExportCommandTest extends FunctionalTestCase
{
    protected array $coreExtensionsToLoad = ['scheduler', 'lowlevel'];
    protected array $testExtensionsToLoad = ['maikschneider/scheduler-as-code'];

    private string $directory;

    protected function setUp(): void
    {
        parent::setUp();
        $this->directory = $this->get(TaskDefinitionProvider::class)->getDirectory();
        @mkdir($this->directory, 0777, true);
        array_map('unlink', glob($this->directory . '/*.yaml') ?: []);
    }

    #[Test]
    public function everyUnlinkedTaskIsExportedAndLinked(): void
    {
        $first = $this->createUnlinkedTask('first', $this->commandTask('first'));
        $second = $this->createUnlinkedTask('second', $this->commandTask('second'));

        $tester = $this->execute([]);

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
        self::assertStringContainsString('Exported 2 task(s)', $tester->getDisplay());
        self::assertSame('cleanup-deletedrecords', $this->findRowByUid($first)['tx_schedulerascode_identifier']);
        self::assertSame('cleanup-deletedrecords-2', $this->findRowByUid($second)['tx_schedulerascode_identifier']);
        self::assertStringContainsString("description: first\n", (string)file_get_contents($this->directory . '/cleanup-deletedrecords.yaml'));
        self::assertStringContainsString("description: second\n", (string)file_get_contents($this->directory . '/cleanup-deletedrecords-2.yaml'));
        self::assertFalse($this->synchronize()->hasChanges());
    }

    #[Test]
    public function taskClassIsTurnedIntoAnIdentifier(): void
    {
        $parameter = (new Typo3Version())->getMajorVersion() >= 14 ? 'selected_tables' : 'selectedTables';
        $uid = $this->createUnlinkedTask('optimize', <<<YAML
            type: 'TYPO3\\CMS\\Scheduler\\Task\\OptimizeDatabaseTableTask'
            execution:
              frequency: 86400
            parameters:
              {$parameter}:
                - sys_log
            YAML);

        $this->execute(['uid' => [(string)$uid]]);

        self::assertSame('optimize-database-table-task', $this->findRowByUid($uid)['tx_schedulerascode_identifier']);
        self::assertStringContainsString('sys_log', (string)file_get_contents($this->directory . '/optimize-database-table-task.yaml'));
    }

    #[Test]
    public function identifierNamesTheFile(): void
    {
        $uid = $this->createUnlinkedTask('cleanup', $this->commandTask());

        $tester = $this->execute(['uid' => [(string)$uid], '--identifier' => 'nightly']);

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
        self::assertFileExists($this->directory . '/nightly.yaml');
        self::assertSame('nightly', $this->findRowByUid($uid)['tx_schedulerascode_identifier']);
    }

    #[Test]
    public function linkedTasksAreSkippedWithoutForce(): void
    {
        $this->writeTask('cleanup', $this->commandTask('from the file'));
        $this->synchronize();
        $uid = $this->findUid('cleanup');
        $this->updateRow($uid, ['description' => 'edited in the backend']);

        $tester = $this->execute(['uid' => [(string)$uid]], OutputInterface::VERBOSITY_VERBOSE);

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
        self::assertStringContainsString(sprintf('Task %d is already linked to "cleanup", skipped.', $uid), $tester->getDisplay());
        self::assertStringContainsString('Nothing to export', $tester->getDisplay());
        self::assertStringContainsString('from the file', (string)file_get_contents($this->directory . '/cleanup.yaml'));
    }

    #[Test]
    public function forceOverwritesTheFileOfALinkedTask(): void
    {
        $this->writeTask('cleanup', $this->commandTask('from the file'));
        $this->synchronize();
        $uid = $this->findUid('cleanup');
        $this->updateRow($uid, ['description' => 'edited in the backend']);

        $tester = $this->execute(['uid' => [(string)$uid], '--force' => true]);

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
        self::assertStringContainsString('edited in the backend', (string)file_get_contents($this->directory . '/cleanup.yaml'));
        self::assertFileDoesNotExist($this->directory . '/cleanup-deletedrecords.yaml');
    }

    #[Test]
    public function identifierNeedsExactlyOneUid(): void
    {
        $tester = $this->execute(['--identifier' => 'nightly']);

        self::assertSame(Command::INVALID, $tester->getStatusCode());
        self::assertStringContainsString('--identifier needs exactly one task uid.', $tester->getDisplay());
    }

    #[Test]
    public function identifierMustBeAValidFileName(): void
    {
        $uid = $this->createUnlinkedTask('cleanup', $this->commandTask());

        $tester = $this->execute(['uid' => [(string)$uid], '--identifier' => 'Nightly Cleanup']);

        self::assertSame(Command::INVALID, $tester->getStatusCode());
        self::assertSame([], glob($this->directory . '/*.yaml'));
    }

    #[Test]
    public function unknownUidIsReported(): void
    {
        $uid = $this->createUnlinkedTask('cleanup', $this->commandTask());

        $tester = $this->execute(['uid' => [(string)$uid, '999']]);

        self::assertSame(Command::FAILURE, $tester->getStatusCode());
        self::assertStringContainsString('No task with uid 999.', $tester->getDisplay());
        self::assertSame([], glob($this->directory . '/*.yaml'));
    }

    #[Test]
    public function taskThatCannotBeReadIsReportedWithoutStoppingOthers(): void
    {
        if ((new Typo3Version())->getMajorVersion() >= 14) {
            self::markTestSkipped('TYPO3 14 stores tasks as plain records, which can always be read.');
        }
        $broken = $this->createUnlinkedTask('broken', $this->commandTask('broken'));
        $this->updateRow($broken, ['serialized_task_object' => 'O:22:"Vendor\\Gone\\MissingTask":0:{}']);
        $working = $this->createUnlinkedTask('working', $this->commandTask('working'));

        $tester = $this->execute([]);

        self::assertSame(Command::FAILURE, $tester->getStatusCode());
        self::assertStringContainsString(sprintf('Scheduler task %d cannot be restored', $broken), $tester->getDisplay());
        self::assertSame('cleanup-deletedrecords', $this->findRowByUid($working)['tx_schedulerascode_identifier']);
    }

    /**
     * @param array<string, mixed> $input
     */
    private function execute(array $input, int $verbosity = OutputInterface::VERBOSITY_NORMAL): CommandTester
    {
        $tester = new CommandTester($this->get(CommandRegistry::class)->get('scheduler:export'));
        $tester->execute($input, ['verbosity' => $verbosity]);
        return $tester;
    }

    /**
     * A task as it exists before anyone exported it: imported from a file, then unlinked and
     * the file removed.
     */
    private function createUnlinkedTask(string $identifier, string $yaml): int
    {
        $this->writeTask($identifier, $yaml);
        $this->synchronize();
        $uid = $this->findUid($identifier);
        $this->updateRow($uid, ['tx_schedulerascode_identifier' => '', 'tx_schedulerascode_hash' => '', 'tx_schedulerascode_record_hash' => '']);
        unlink($this->directory . '/' . $identifier . '.yaml');
        return $uid;
    }

    private function commandTask(string $description = ''): string
    {
        return "type: 'cleanup:deletedrecords'\ndescription: '$description'\nexecution:\n  frequency: '0 3 * * *'\n";
    }

    private function writeTask(string $identifier, string $yaml): void
    {
        file_put_contents($this->directory . '/' . $identifier . '.yaml', $yaml);
    }

    private function synchronize(): \MaikSchneider\SchedulerAsCode\Service\SynchronizationResult
    {
        return $this->get(TaskSynchronizer::class)->synchronize($this->get(TaskDefinitionProvider::class)->getDefinitions());
    }

    /**
     * @param array<string, mixed> $fields
     */
    private function updateRow(int $uid, array $fields): void
    {
        $this->get(ConnectionPool::class)->getConnectionForTable('tx_scheduler_task')->update('tx_scheduler_task', $fields, ['uid' => $uid]);
    }

    private function findUid(string $identifier): int
    {
        $uid = $this->get(ConnectionPool::class)->getConnectionForTable('tx_scheduler_task')
            ->select(['uid'], 'tx_scheduler_task', ['tx_schedulerascode_identifier' => $identifier])->fetchOne();
        self::assertNotFalse($uid, 'No task linked to "' . $identifier . '"');
        return (int)$uid;
    }

    /**
     * @return array<string, mixed>
     */
    private function findRowByUid(int $uid): array
    {
        $row = $this->get(ConnectionPool::class)->getConnectionForTable('tx_scheduler_task')
            ->select(['*'], 'tx_scheduler_task', ['uid' => $uid])->fetchAssociative();
        self::assertIsArray($row);
        return $row;
    }
}
