<?php

declare(strict_types=1);

namespace MaikSchneider\SchedulerAsCode\Tests\Unit\Configuration;

use MaikSchneider\SchedulerAsCode\Configuration\ExecutionMapper;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

final class ExecutionMapperTest extends UnitTestCase
{
    #[Test]
    public function cronTaskWithoutStartIsScheduledByItsExpressionNotForNow(): void
    {
        $now = time();
        $execution = (new ExecutionMapper())->toExecution(['frequency' => '0 3 * * *'], $now);

        $next = (int)$execution->getNextExecution();

        self::assertGreaterThan($now, $next);
        self::assertSame('03:00', date('H:i', $next));
    }

    #[Test]
    public function intervalTaskWithoutStartRunsAfterOneInterval(): void
    {
        $now = time();
        $execution = (new ExecutionMapper())->toExecution(['frequency' => 3600], $now);

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
}
