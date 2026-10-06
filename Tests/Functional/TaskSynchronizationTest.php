<?php

declare(strict_types=1);

namespace MaikSchneider\SchedulerAsCode\Tests\Functional;

use MaikSchneider\SchedulerAsCode\Configuration\TaskDefinitionProvider;
use MaikSchneider\SchedulerAsCode\Service\TaskExporter;
use MaikSchneider\SchedulerAsCode\Service\TaskStateResolver;
use MaikSchneider\SchedulerAsCode\Service\TaskSynchronizer;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Information\Typo3Version;
use TYPO3\CMS\Scheduler\Domain\Repository\SchedulerTaskRepository;
use TYPO3\CMS\Scheduler\Execution;
use TYPO3\CMS\Scheduler\Task\ExecuteSchedulableCommandTask;
use TYPO3\CMS\Scheduler\Task\OptimizeDatabaseTableTask;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

final class TaskSynchronizationTest extends FunctionalTestCase
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
    public function newFileCreatesATaskTheSchedulerCanLoad(): void
    {
        $this->writeTask('cleanup', <<<YAML
            type: 'cleanup:deletedrecords'
            description: 'Remove deleted records'
            group: 'Maintenance'
            execution:
              frequency: '0 3 * * *'
            parameters:
              options:
                min-age: 30
            YAML);

        $result = $this->synchronize();

        self::assertSame(['cleanup'], $result->created);
        $row = $this->findRow('cleanup');
        self::assertSame('Remove deleted records', $row['description']);
        self::assertSame(0, (int)$row['disable']);
        self::assertGreaterThan(time(), (int)$row['nextexecution']);

        $task = $this->get(SchedulerTaskRepository::class)->findByUid((int)$row['uid']);
        self::assertInstanceOf(ExecuteSchedulableCommandTask::class, $task);
        self::assertSame(['min-age' => true], array_filter($task->getOptions()));
        self::assertSame(30, $task->getOptionValues()['min-age']);
        $execution = $task->getExecution();
        self::assertInstanceOf(Execution::class, $execution);
        self::assertSame('0 3 * * *', $execution->getCronCmd());
        self::assertSame('Maintenance', $this->findGroupName((int)$row['task_group']));
    }

    #[Test]
    public function nativeTaskParametersAreApplied(): void
    {
        $parameter = $this->isTypo3v14() ? 'selected_tables' : 'selectedTables';
        $this->writeTask('optimize', <<<YAML
            type: 'TYPO3\\CMS\\Scheduler\\Task\\OptimizeDatabaseTableTask'
            execution:
              frequency: 86400
            parameters:
              {$parameter}:
                - sys_log
                - sys_history
            YAML);

        $this->synchronize();

        $task = $this->get(SchedulerTaskRepository::class)->findByUid((int)$this->findRow('optimize')['uid']);
        self::assertInstanceOf(OptimizeDatabaseTableTask::class, $task);
        $execution = $task->getExecution();
        self::assertInstanceOf(Execution::class, $execution);
        self::assertSame(86400, (int)$execution->getInterval());
        self::assertStringContainsString('sys_history', $task->getAdditionalInformation());
    }

    #[Test]
    public function unchangedFileLeavesTheTaskAlone(): void
    {
        $this->writeTask('cleanup', $this->minimalTask());
        $this->synchronize();

        $result = $this->synchronize();

        self::assertFalse($result->hasChanges());
    }

    #[Test]
    public function changedFileUpdatesTheSameRecord(): void
    {
        $this->writeTask('cleanup', $this->minimalTask('first'));
        $this->synchronize();
        $uid = (int)$this->findRow('cleanup')['uid'];

        $this->writeTask('cleanup', $this->minimalTask('second'));
        $result = $this->synchronize();

        self::assertSame(['cleanup'], $result->updated);
        self::assertSame($uid, (int)$this->findRow('cleanup')['uid']);
        self::assertSame('second', $this->findRow('cleanup')['description']);
    }

    #[Test]
    public function removedFileDisablesTheTaskAndRestoredFileEnablesIt(): void
    {
        $this->writeTask('cleanup', $this->minimalTask());
        $this->synchronize();

        unlink($this->directory . '/cleanup.yaml');
        $result = $this->synchronize();

        self::assertSame(['cleanup'], $result->disabled);
        self::assertSame(1, (int)$this->findRow('cleanup')['disable']);
        self::assertSame('', $this->findRow('cleanup')['tx_schedulerascode_hash']);

        $this->writeTask('cleanup', $this->minimalTask());
        $result = $this->synchronize();

        self::assertSame(['cleanup'], $result->updated);
        self::assertSame(0, (int)$this->findRow('cleanup')['disable']);
    }

    #[Test]
    public function taskDeletedInTheBackendComesBackWhileItsFileExists(): void
    {
        $this->writeTask('cleanup', $this->minimalTask());
        $this->synchronize();
        $this->getConnectionPool()->getConnectionForTable('tx_scheduler_task')
            ->update('tx_scheduler_task', ['deleted' => 1], ['tx_schedulerascode_identifier' => 'cleanup']);

        $this->synchronize();

        self::assertSame(0, (int)$this->findRow('cleanup')['deleted']);
    }

    #[Test]
    public function unknownTypeIsReportedWithoutStoppingOtherTasks(): void
    {
        $this->writeTask('broken', "type: 'does:not-exist'\nexecution:\n  frequency: 60\n");
        $this->writeTask('cleanup', $this->minimalTask());

        $result = $this->synchronize();

        self::assertArrayHasKey('broken', $result->failed);
        self::assertSame(['cleanup'], $result->created);
    }

    #[Test]
    public function unknownParameterOfATaskClassIsReported(): void
    {
        if ($this->isTypo3v14()) {
            self::markTestSkipped('TYPO3 14 keeps parameters without a column in the "parameters" field.');
        }
        $this->writeTask('optimize', <<<YAML
            type: 'TYPO3\\CMS\\Scheduler\\Task\\OptimizeDatabaseTableTask'
            execution:
              frequency: 86400
            parameters:
              tablesToOptimize: [sys_log]
            YAML);

        $result = $this->synchronize();

        self::assertStringContainsString('has no parameter "tablesToOptimize"', $result->failed['optimize'] ?? '');
        self::assertSame([], $result->created);
    }

    #[Test]
    public function invalidDateIsReportedWithoutStoppingOtherTasks(): void
    {
        $this->writeTask('broken', "type: 'cleanup:deletedrecords'\nexecution:\n  frequency: 60\n  start: 'next full moon'\n");
        $this->writeTask('cleanup', $this->minimalTask());

        $result = $this->synchronize();

        self::assertStringContainsString('"next full moon" is not a valid date.', $result->failed['broken'] ?? '');
        self::assertSame(['cleanup'], $result->created);
    }

    #[Test]
    public function tasksOfTheSameGroupShareOneGroupRecord(): void
    {
        $this->writeTask('first', "type: 'cleanup:deletedrecords'\ngroup: 'Maintenance'\nexecution:\n  frequency: 60\n");
        $this->writeTask('second', "type: 'cleanup:deletedrecords'\ngroup: 'Maintenance'\nexecution:\n  frequency: 120\n");

        $this->synchronize();

        $group = (int)$this->findRow('first')['task_group'];
        self::assertGreaterThan(0, $group);
        self::assertSame($group, (int)$this->findRow('second')['task_group']);
        self::assertSame(1, $this->getConnectionPool()->getConnectionForTable('tx_scheduler_task_group')->count('uid', 'tx_scheduler_task_group', []));
    }

    #[Test]
    public function taskWithoutGroupHasNone(): void
    {
        $this->writeTask('cleanup', $this->minimalTask());

        $this->synchronize();

        self::assertSame(0, (int)$this->findRow('cleanup')['task_group']);
    }

    #[Test]
    public function fileCanDisableItsTask(): void
    {
        $this->writeTask('cleanup', "type: 'cleanup:deletedrecords'\ndisabled: true\nexecution:\n  frequency: '0 3 * * *'\n");

        $this->synchronize();

        self::assertSame(1, (int)$this->findRow('cleanup')['disable']);
    }

    #[Test]
    public function orphanedTaskIsDisabledOnlyOnce(): void
    {
        $this->writeTask('cleanup', $this->minimalTask());
        $this->synchronize();
        unlink($this->directory . '/cleanup.yaml');
        $this->synchronize();

        $result = $this->synchronize();

        self::assertFalse($result->hasChanges());
    }

    #[Test]
    public function taskPastItsEndIsImportedDisabled(): void
    {
        $this->writeTask('cleanup', "type: 'cleanup:deletedrecords'\nexecution:\n  frequency: 60\n  start: '2020-01-01'\n  end: '2021-01-01'\n");

        $result = $this->synchronize();

        self::assertSame(['cleanup'], $result->created);
        self::assertSame(1, (int)$this->findRow('cleanup')['disable']);
    }

    #[Test]
    public function priorityIsImportedAndExported(): void
    {
        if (!$this->isTypo3v14()) {
            self::markTestSkipped('Task priorities exist since TYPO3 14.');
        }
        $this->writeTask('cleanup', "type: 'cleanup:deletedrecords'\npriority: 50\nexecution:\n  frequency: 60\n");
        $this->synchronize();
        $row = $this->findRow('cleanup');

        self::assertSame(50, (int)$row['priority']);
        self::assertStringContainsString('priority: 50', (string)file_get_contents($this->get(TaskExporter::class)->export($row)));
        self::assertFalse($this->synchronize()->hasChanges());
    }

    #[Test]
    public function exportedNativeTaskImportsAsUnchanged(): void
    {
        [$table, $days] = $this->isTypo3v14() ? ['table', 'number_of_days'] : ['table', 'numberOfDays'];
        $this->writeTask('garbage', <<<YAML
            type: 'TYPO3\\CMS\\Scheduler\\Task\\TableGarbageCollectionTask'
            execution:
              frequency: 86400
            parameters:
              {$table}: sys_log
              {$days}: 30
            YAML);
        $this->synchronize();
        $row = $this->findRow('garbage');

        $exported = (string)file_get_contents($this->get(TaskExporter::class)->export($row));

        self::assertStringContainsString($table . ': sys_log', $exported);
        self::assertStringContainsString($days . ': 30', $exported);
        self::assertFalse($this->synchronize()->hasChanges());
        self::assertFalse($this->get(TaskStateResolver::class)->getStates()[(int)$row['uid']]['stale']);
    }

    #[Test]
    public function exportedTaskImportsAsUnchanged(): void
    {
        $this->writeTask('cleanup', <<<YAML
            type: 'cleanup:deletedrecords'
            description: 'Remove deleted records'
            group: 'Maintenance'
            execution:
              frequency: 3600
              start: '2026-01-01 04:00'
              multiple: true
            parameters:
              options:
                min-age: 30
                dry-run: true
            YAML);
        $this->synchronize();
        $row = $this->findRow('cleanup');
        $this->getConnectionPool()->getConnectionForTable('tx_scheduler_task')
            ->update('tx_scheduler_task', ['tx_schedulerascode_identifier' => '', 'tx_schedulerascode_hash' => ''], ['uid' => $row['uid']]);
        unlink($this->directory . '/cleanup.yaml');

        $file = $this->get(TaskExporter::class)->export($this->findRowByUid((int)$row['uid']));

        self::assertSame($this->directory . '/cleanup-deletedrecords.yaml', $file);
        $exported = (string)file_get_contents($file);
        self::assertStringContainsString("type: 'cleanup:deletedrecords'", $exported);
        self::assertStringContainsString('group: Maintenance', $exported);
        self::assertStringContainsString('min-age: 30', $exported);
        self::assertStringContainsString('dry-run: true', $exported);
        self::assertStringContainsString('multiple: true', $exported);
        self::assertFalse($this->synchronize()->hasChanges());
        self::assertSame((int)$row['uid'], (int)$this->findRow('cleanup-deletedrecords')['uid']);
    }

    private function synchronize(): \MaikSchneider\SchedulerAsCode\Service\SynchronizationResult
    {
        return $this->get(TaskSynchronizer::class)->synchronize($this->get(TaskDefinitionProvider::class)->getDefinitions());
    }

    private function writeTask(string $identifier, string $yaml): void
    {
        file_put_contents($this->directory . '/' . $identifier . '.yaml', $yaml);
    }

    private function minimalTask(string $description = ''): string
    {
        return "type: 'cleanup:deletedrecords'\ndescription: '$description'\nexecution:\n  frequency: '0 3 * * *'\n";
    }

    /**
     * @return array<string, mixed>
     */
    private function findRow(string $identifier): array
    {
        $queryBuilder = $this->getConnectionPool()->getQueryBuilderForTable('tx_scheduler_task');
        $queryBuilder->getRestrictions()->removeAll();
        $row = $queryBuilder->select('*')->from('tx_scheduler_task')
            ->where($queryBuilder->expr()->eq('tx_schedulerascode_identifier', $queryBuilder->createNamedParameter($identifier)))
            ->executeQuery()->fetchAssociative();
        self::assertIsArray($row, 'No task linked to "' . $identifier . '"');
        return $row;
    }

    /**
     * @return array<string, mixed>
     */
    private function findRowByUid(int $uid): array
    {
        $row = $this->getConnectionPool()->getConnectionForTable('tx_scheduler_task')
            ->select(['*'], 'tx_scheduler_task', ['uid' => $uid])->fetchAssociative();
        self::assertIsArray($row);
        return $row;
    }

    private function findGroupName(int $uid): string
    {
        return (string)$this->getConnectionPool()->getConnectionForTable('tx_scheduler_task_group')
            ->select(['groupName'], 'tx_scheduler_task_group', ['uid' => $uid])->fetchOne();
    }

    private function isTypo3v14(): bool
    {
        return (new Typo3Version())->getMajorVersion() >= 14;
    }

    protected function getConnectionPool(): ConnectionPool
    {
        return $this->get(ConnectionPool::class);
    }
}
