<?php

declare(strict_types=1);

namespace MaikSchneider\SchedulerAsCode\Tests\Unit\Service;

use MaikSchneider\SchedulerAsCode\Persistence\ManagedTaskRepository;
use MaikSchneider\SchedulerAsCode\Persistence\UnknownTaskTypeException;
use MaikSchneider\SchedulerAsCode\Service\TaskSnapshot;
use MaikSchneider\SchedulerAsCode\Service\TaskStateResolver;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\Stub;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

final class TaskStateResolverTest extends UnitTestCase
{
    private ManagedTaskRepository&Stub $repository;
    private TaskSnapshot&Stub $snapshot;
    private TaskStateResolver $subject;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = self::createStub(ManagedTaskRepository::class);
        $this->snapshot = self::createStub(TaskSnapshot::class);
        $this->subject = new TaskStateResolver($this->repository, $this->snapshot);
    }

    #[Test]
    public function statesAreKeyedByUid(): void
    {
        $this->repository->method('findManagedTasks')->willReturn([
            $this->row(7, 'cleanup', 'file-hash', 'record-hash'),
        ]);
        $this->snapshot->method('getHash')->willReturn('record-hash');

        self::assertSame(
            [7 => ['identifier' => 'cleanup', 'source' => 'config/scheduler/cleanup.yaml', 'orphaned' => false, 'stale' => false]],
            $this->subject->getStates()
        );
    }

    #[Test]
    public function recordChangedSinceTheImportIsStale(): void
    {
        $this->repository->method('findManagedTasks')->willReturn([$this->row(7, 'cleanup', 'file-hash', 'record-hash')]);
        $this->snapshot->method('getHash')->willReturn('edited');

        self::assertTrue($this->subject->getStates()[7]['stale']);
    }

    #[Test]
    public function orphanedTaskIsNeverStale(): void
    {
        $this->repository->method('findManagedTasks')->willReturn([$this->row(7, 'cleanup', '', 'record-hash')]);
        $this->snapshot->method('getHash')->willReturn('edited');

        $state = $this->subject->getStates()[7];

        self::assertTrue($state['orphaned']);
        self::assertFalse($state['stale']);
    }

    #[Test]
    public function taskWithoutRecordHashIsNotStale(): void
    {
        $this->snapshot->method('getHash')->willReturn('edited');

        self::assertFalse($this->subject->isStale($this->row(7, 'cleanup', 'file-hash', '')));
    }

    #[Test]
    public function taskThatCannotBeReadIsNotStale(): void
    {
        $this->snapshot->method('getHash')->willThrowException(new UnknownTaskTypeException('gone', 1));

        self::assertFalse($this->subject->isStale($this->row(7, 'cleanup', 'file-hash', 'record-hash')));
    }

    /**
     * @return array<string, mixed>
     */
    private function row(int $uid, string $identifier, string $hash, string $recordHash): array
    {
        return [
            'uid' => $uid,
            'tx_schedulerascode_identifier' => $identifier,
            'tx_schedulerascode_source' => 'config/scheduler/' . $identifier . '.yaml',
            'tx_schedulerascode_hash' => $hash,
            'tx_schedulerascode_record_hash' => $recordHash,
        ];
    }
}
