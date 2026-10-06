<?php

declare(strict_types=1);

namespace MaikSchneider\SchedulerAsCode\Tests\Functional;

use MaikSchneider\SchedulerAsCode\Configuration\TaskDefinitionProvider;
use MaikSchneider\SchedulerAsCode\Service\TaskExporter;
use MaikSchneider\SchedulerAsCode\Service\TaskStateResolver;
use MaikSchneider\SchedulerAsCode\Service\TaskSynchronizer;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Scheduler\Domain\Repository\SchedulerTaskRepository;
use TYPO3\CMS\Scheduler\Scheduler;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

final class StaleDetectionTest extends FunctionalTestCase
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
    public function importedTaskIsInSync(): void
    {
        $uid = $this->import($this->recurringTask());

        self::assertFalse($this->getState($uid)['stale']);
    }

    #[Test]
    public function editedDescriptionMakesTheTaskStale(): void
    {
        $uid = $this->import($this->recurringTask());

        $this->updateRow($uid, ['description' => 'edited in the backend']);

        self::assertTrue($this->getState($uid)['stale']);
    }

    #[Test]
    public function disablingARecurringTaskMakesItStale(): void
    {
        $uid = $this->import($this->recurringTask());

        $this->updateRow($uid, ['disable' => 1]);

        self::assertTrue($this->getState($uid)['stale']);
    }

    #[Test]
    public function changedFileBringsTheTaskBackInSync(): void
    {
        $uid = $this->import($this->recurringTask());
        $this->updateRow($uid, ['description' => 'edited in the backend']);

        $this->writeTask($this->recurringTask('changed in the file'));
        $this->synchronize();

        self::assertFalse($this->getState($uid)['stale']);
    }

    #[Test]
    public function exportBringsTheTaskBackInSync(): void
    {
        $uid = $this->import($this->recurringTask());
        $this->updateRow($uid, ['description' => 'edited in the backend']);

        $this->get(TaskExporter::class)->export($this->findRow($uid));

        self::assertFalse($this->getState($uid)['stale']);
        self::assertStringContainsString('edited in the backend', (string)file_get_contents($this->directory . '/cleanup.yaml'));
    }

    #[Test]
    public function executingTheTaskKeepsItInSync(): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/be_users.csv');
        $this->setUpBackendUser(1);
        $uid = $this->import($this->recurringTask());
        $task = $this->get(SchedulerTaskRepository::class)->findByUid($uid);

        $this->get(Scheduler::class)->executeTask($task);

        self::assertGreaterThan(0, (int)$this->findRow($uid)['lastexecution_time']);
        self::assertFalse($this->getState($uid)['stale']);
    }

    #[Test]
    public function singleRunTaskIsInSyncAfterItsRun(): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/be_users.csv');
        $this->setUpBackendUser(1);
        $uid = $this->import("type: 'cleanup:deletedrecords'\nexecution:\n  start: '2026-01-01 04:00'\n");
        $task = $this->get(SchedulerTaskRepository::class)->findByUid($uid);

        $this->get(Scheduler::class)->executeTask($task);

        self::assertGreaterThan(0, (int)$this->findRow($uid)['lastexecution_time']);
        self::assertFalse($this->getState($uid)['stale']);
    }

    #[Test]
    public function disablingASingleRunTaskBeforeItsRunMakesItStale(): void
    {
        $uid = $this->import("type: 'cleanup:deletedrecords'\nexecution:\n  start: '2030-01-01 04:00'\n");

        $this->updateRow($uid, ['disable' => 1]);

        self::assertTrue($this->getState($uid)['stale']);
    }

    #[Test]
    public function taskLinkedBeforeStaleDetectionIsNotReported(): void
    {
        $uid = $this->import($this->recurringTask());
        $this->updateRow($uid, ['tx_schedulerascode_record_hash' => '', 'description' => 'edited in the backend']);

        self::assertFalse($this->getState($uid)['stale']);
    }

    private function recurringTask(string $description = 'from the file'): string
    {
        return "type: 'cleanup:deletedrecords'\ndescription: '$description'\nexecution:\n  frequency: '0 3 * * *'\n";
    }

    private function import(string $yaml): int
    {
        $this->writeTask($yaml);
        $this->synchronize();
        $queryBuilder = $this->get(ConnectionPool::class)->getQueryBuilderForTable('tx_scheduler_task');
        $queryBuilder->getRestrictions()->removeAll();
        return (int)$queryBuilder->select('uid')->from('tx_scheduler_task')
            ->where($queryBuilder->expr()->eq('tx_schedulerascode_identifier', $queryBuilder->createNamedParameter('cleanup')))
            ->executeQuery()->fetchOne();
    }

    private function writeTask(string $yaml): void
    {
        file_put_contents($this->directory . '/cleanup.yaml', $yaml);
    }

    private function synchronize(): void
    {
        $this->get(TaskSynchronizer::class)->synchronize($this->get(TaskDefinitionProvider::class)->getDefinitions());
    }

    /**
     * @param array<string, mixed> $fields
     */
    private function updateRow(int $uid, array $fields): void
    {
        $this->get(ConnectionPool::class)->getConnectionForTable('tx_scheduler_task')->update('tx_scheduler_task', $fields, ['uid' => $uid]);
    }

    /**
     * @return array<string, mixed>
     */
    private function findRow(int $uid): array
    {
        $queryBuilder = $this->get(ConnectionPool::class)->getQueryBuilderForTable('tx_scheduler_task');
        $queryBuilder->getRestrictions()->removeAll();
        $row = $queryBuilder->select('*')->from('tx_scheduler_task')
            ->where($queryBuilder->expr()->eq('uid', $uid))
            ->executeQuery()->fetchAssociative();
        self::assertIsArray($row);
        return $row;
    }

    /**
     * @return array{identifier: string, source: string, orphaned: bool, stale: bool}
     */
    private function getState(int $uid): array
    {
        $states = $this->get(TaskStateResolver::class)->getStates();
        self::assertArrayHasKey($uid, $states);
        return $states[$uid];
    }
}
