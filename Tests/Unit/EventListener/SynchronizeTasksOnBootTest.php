<?php

declare(strict_types=1);

namespace MaikSchneider\SchedulerAsCode\Tests\Unit\EventListener;

use MaikSchneider\SchedulerAsCode\Configuration\InvalidTaskDefinitionException;
use MaikSchneider\SchedulerAsCode\Configuration\TaskDefinition;
use MaikSchneider\SchedulerAsCode\Configuration\TaskDefinitionProvider;
use MaikSchneider\SchedulerAsCode\EventListener\SynchronizeTasksOnBoot;
use MaikSchneider\SchedulerAsCode\Service\SynchronizationResult;
use MaikSchneider\SchedulerAsCode\Service\TaskSynchronizer;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\MockObject\Stub;
use Psr\Log\LoggerInterface;
use TYPO3\CMS\Core\Cache\Frontend\FrontendInterface;
use TYPO3\CMS\Core\Core\Event\BootCompletedEvent;
use TYPO3\CMS\Core\Locking\Exception\LockAcquireWouldBlockException;
use TYPO3\CMS\Core\Locking\LockFactory;
use TYPO3\CMS\Core\Locking\LockingStrategyInterface;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

final class SynchronizeTasksOnBootTest extends UnitTestCase
{
    private TaskDefinitionProvider&Stub $provider;
    private TaskSynchronizer&MockObject $synchronizer;
    private LockingStrategyInterface&MockObject $locker;
    /** @var array<string, mixed> */
    private array $cacheEntries = [];
    /** @var list<array{string, string, array<string, mixed>}> */
    private array $logs = [];
    private SynchronizeTasksOnBoot $subject;

    protected function setUp(): void
    {
        parent::setUp();
        $this->provider = self::createStub(TaskDefinitionProvider::class);
        $this->provider->method('getFingerprint')->willReturn('fingerprint-1');
        $this->synchronizer = $this->createMock(TaskSynchronizer::class);
        $this->locker = $this->createMock(LockingStrategyInterface::class);
        $lockFactory = self::createStub(LockFactory::class);
        $lockFactory->method('createLocker')->willReturn($this->locker);

        $cache = self::createStub(FrontendInterface::class);
        $cache->method('get')->willReturnCallback(fn (string $entry): mixed => $this->cacheEntries[$entry] ?? false);
        $cache->method('set')->willReturnCallback(function (string $entry, mixed $value): void {
            $this->cacheEntries[$entry] = $value;
        });

        $logger = self::createStub(LoggerInterface::class);
        foreach (['info', 'warning', 'error'] as $level) {
            $logger->method($level)->willReturnCallback(function (string|\Stringable $message, array $context = []) use ($level): void {
                $this->logs[] = [$level, (string)$message, $context];
            });
        }

        $this->subject = new SynchronizeTasksOnBoot($this->provider, $this->synchronizer, $cache, $lockFactory, $logger);
    }

    #[Test]
    public function unchangedFingerprintSkipsTheImportWithoutLocking(): void
    {
        $this->cacheEntries['fingerprint'] = 'fingerprint-1';
        $this->locker->expects(self::never())->method('acquire');
        $this->synchronizer->expects(self::never())->method('synchronize');

        ($this->subject)(new BootCompletedEvent(false));
    }

    #[Test]
    public function changedFingerprintImportsAndRemembersIt(): void
    {
        $definitions = ['cleanup' => new TaskDefinition('cleanup', [], 'cleanup.yaml')];
        $this->provider->method('getDefinitions')->willReturn($definitions);
        $this->locker->method('acquire')->willReturn(true);
        $this->locker->expects(self::once())->method('release');
        $result = new SynchronizationResult();
        $result->created[] = 'cleanup';
        $this->synchronizer->expects(self::once())->method('synchronize')->with($definitions)->willReturn($result);

        ($this->subject)(new BootCompletedEvent(false));

        self::assertSame('fingerprint-1', $this->cacheEntries['fingerprint']);
        self::assertSame([['info', 'Scheduler task files imported.', ['created' => ['cleanup'], 'updated' => [], 'disabled' => []]]], $this->logs);
    }

    #[Test]
    public function importWithoutChangesLogsNothing(): void
    {
        $this->provider->method('getDefinitions')->willReturn([]);
        $this->locker->method('acquire')->willReturn(true);
        $this->locker->expects(self::once())->method('release');
        $this->synchronizer->expects(self::once())->method('synchronize')->willReturn(new SynchronizationResult());

        ($this->subject)(new BootCompletedEvent(false));

        self::assertSame([], $this->logs);
    }

    #[Test]
    public function tasksThatFailedAreLoggedOneByOne(): void
    {
        $this->provider->method('getDefinitions')->willReturn([]);
        $this->locker->method('acquire')->willReturn(true);
        $this->locker->expects(self::once())->method('release');
        $result = new SynchronizationResult();
        $result->failed = ['first' => 'unknown type', 'second' => 'invalid date'];
        $this->synchronizer->expects(self::once())->method('synchronize')->willReturn($result);

        ($this->subject)(new BootCompletedEvent(false));

        self::assertSame(['error', 'error'], array_column($this->logs, 0));
        self::assertSame(['identifier' => 'second', 'reason' => 'invalid date'], $this->logs[1][2]);
        self::assertSame('fingerprint-1', $this->cacheEntries['fingerprint']);
    }

    #[Test]
    public function lockHeldByAnotherProcessSkipsTheImport(): void
    {
        $this->locker->expects(self::once())->method('acquire')->willReturn(false);
        $this->locker->expects(self::never())->method('release');
        $this->synchronizer->expects(self::never())->method('synchronize');

        ($this->subject)(new BootCompletedEvent(false));

        self::assertArrayNotHasKey('fingerprint', $this->cacheEntries);
    }

    #[Test]
    public function lockThatWouldBlockSkipsTheImport(): void
    {
        $this->locker->expects(self::once())->method('acquire')->willThrowException(new LockAcquireWouldBlockException('busy', 1));
        $this->locker->expects(self::never())->method('release');
        $this->synchronizer->expects(self::never())->method('synchronize');

        ($this->subject)(new BootCompletedEvent(false));

        self::assertArrayNotHasKey('fingerprint', $this->cacheEntries);
    }

    #[Test]
    public function importDoneByAnotherProcessWhileWaitingForTheLockIsNotRepeated(): void
    {
        $this->locker->method('acquire')->willReturnCallback(function (): bool {
            $this->cacheEntries['fingerprint'] = 'fingerprint-1';
            return true;
        });
        $this->locker->expects(self::once())->method('release');
        $this->synchronizer->expects(self::never())->method('synchronize');

        ($this->subject)(new BootCompletedEvent(false));
    }

    #[Test]
    public function invalidTaskFileIsLoggedOnceAndNotRetriedUntilItChanges(): void
    {
        $this->provider->method('getDefinitions')->willThrowException(new InvalidTaskDefinitionException('broken file', 1));
        $this->locker->method('acquire')->willReturn(true);
        $this->locker->expects(self::once())->method('release');
        $this->synchronizer->expects(self::never())->method('synchronize');

        ($this->subject)(new BootCompletedEvent(false));
        ($this->subject)(new BootCompletedEvent(false));

        self::assertSame('fingerprint-1', $this->cacheEntries['fingerprint']);
        self::assertCount(1, $this->logs);
        self::assertSame(['error', 'Scheduler task files were not imported: {message}', ['message' => 'broken file']], $this->logs[0]);
    }

    #[Test]
    public function unexpectedFailureIsRetriedOnTheNextBoot(): void
    {
        $this->provider->method('getDefinitions')->willReturn([]);
        $this->locker->method('acquire')->willReturn(true);
        $this->locker->expects(self::exactly(2))->method('release');
        $this->synchronizer->expects(self::exactly(2))->method('synchronize')
            ->willThrowException(new \RuntimeException('Table tx_scheduler_task has no column tx_schedulerascode_hash'));

        ($this->subject)(new BootCompletedEvent(false));
        ($this->subject)(new BootCompletedEvent(false));

        self::assertArrayNotHasKey('fingerprint', $this->cacheEntries);
        self::assertSame(['warning', 'warning'], array_column($this->logs, 0));
    }
}
