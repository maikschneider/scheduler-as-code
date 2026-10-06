<?php

declare(strict_types=1);

namespace MaikSchneider\SchedulerAsCode\Tests\Unit\Configuration;

use MaikSchneider\SchedulerAsCode\Configuration\ExecutionMapper;
use MaikSchneider\SchedulerAsCode\Configuration\InvalidTaskDefinitionException;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

final class ExecutionMapperTest extends UnitTestCase
{
    #[Test]
    public function cronTaskWithoutStartIsScheduledByItsExpressionNotForNow(): void
    {
        $now = time();
        $execution = (new ExecutionMapper())->toExecution(['frequency' => '0 3 * * *'], $now);
        self::assertSame($now - 1, (int)$execution->getStart());

        $next = (int)$execution->getNextExecution();

        self::assertGreaterThan($now, $next);
        self::assertSame('03:00', date('H:i', $next));
    }

    #[Test]
    public function intervalTaskWithoutStartRunsAfterOneInterval(): void
    {
        $now = time();
        $execution = (new ExecutionMapper())->toExecution(['frequency' => 3600], $now);
        self::assertSame($now - 1, (int)$execution->getStart());

        self::assertGreaterThan($now + 3500, (int)$execution->getNextExecution());
    }

    #[Test]
    public function explicitStartInTheFutureIsTheFirstRun(): void
    {
        $start = time() + 86400;
        $execution = (new ExecutionMapper())->toExecution(['frequency' => '0 3 * * *', 'start' => $start], time());

        self::assertSame($start, (int)$execution->getNextExecution());
    }

    #[Test]
    public function singleRunIsScheduledForItsStart(): void
    {
        $start = time() + 600;
        $execution = (new ExecutionMapper())->toExecution(['start' => $start], time());

        self::assertSame($start, (int)$execution->getNextExecution());
    }

    #[Test]
    public function exportOmitsTheStartOfCronTasks(): void
    {
        $mapper = new ExecutionMapper();

        $configuration = $mapper->fromExecution($mapper->toExecution(['frequency' => '*/15 * * * *', 'multiple' => true], time()));

        self::assertSame(['frequency' => '*/15 * * * *', 'multiple' => true], $configuration);
    }

    #[Test]
    public function numericStringFrequencyIsAnInterval(): void
    {
        $execution = (new ExecutionMapper())->toExecution(['frequency' => '3600'], time());

        self::assertSame(3600, (int)$execution->getInterval());
        self::assertSame('', (string)$execution->getCronCmd());
    }

    #[Test]
    public function taskWithoutFrequencyIsASingleRun(): void
    {
        $execution = (new ExecutionMapper())->toExecution(['frequency' => '', 'start' => '2030-01-01 04:00'], time());

        self::assertSame(0, (int)$execution->getInterval());
        self::assertSame('', (string)$execution->getCronCmd());
        self::assertTrue((bool)$execution->getIsNewSingleExecution());
        self::assertSame((new \DateTimeImmutable('2030-01-01 04:00'))->getTimestamp(), (int)$execution->getStart());
    }

    #[Test]
    public function datesAreParsedAndDefaultsApplied(): void
    {
        $mapper = new ExecutionMapper();
        $execution = $mapper->toExecution(['frequency' => 60, 'end' => '2030-12-31'], time());

        self::assertSame((new \DateTimeImmutable('2030-12-31'))->getTimestamp(), (int)$execution->getEnd());
        self::assertArrayNotHasKey('multiple', $mapper->fromExecution($execution));
    }

    #[Test]
    public function taskWithoutEndNeverEnds(): void
    {
        self::assertSame(0, (int)(new ExecutionMapper())->toExecution(['frequency' => 60], time())->getEnd());
    }

    #[Test]
    public function invalidDateIsRejected(): void
    {
        $this->expectException(InvalidTaskDefinitionException::class);
        $this->expectExceptionCode(1791360008);

        (new ExecutionMapper())->toExecution(['frequency' => 60, 'start' => 'not a date'], time());
    }

    #[Test]
    public function exportKeepsTheStartAndEndOfIntervalTasks(): void
    {
        $mapper = new ExecutionMapper();
        $start = 1893470400;
        $end = 1924992000;

        $configuration = $mapper->fromExecution($mapper->toExecution(['frequency' => 3600, 'start' => $start, 'end' => $end], time()));

        self::assertSame(['frequency' => 3600, 'start' => date(DATE_ATOM, $start), 'end' => date(DATE_ATOM, $end)], $configuration);
    }

    #[Test]
    public function exportOfASingleRunKeepsOnlyItsStart(): void
    {
        $mapper = new ExecutionMapper();
        $start = 1893470400;

        self::assertSame(['start' => date(DATE_ATOM, $start)], $mapper->fromExecution($mapper->toExecution(['start' => $start], time())));
    }

    #[Test]
    public function exportedDatesImportAsTheSameTimestamps(): void
    {
        $mapper = new ExecutionMapper();
        $start = 1893470400;
        $end = 1924992000;

        $exported = $mapper->fromExecution($mapper->toExecution(['frequency' => 3600, 'start' => $start, 'end' => $end], time()));
        $execution = $mapper->toExecution($exported, time());

        self::assertSame($start, (int)$execution->getStart());
        self::assertSame($end, (int)$execution->getEnd());
    }
}
