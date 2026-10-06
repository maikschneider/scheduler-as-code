<?php

declare(strict_types=1);

namespace MaikSchneider\SchedulerAsCode\Tests\Unit\Service;

use MaikSchneider\SchedulerAsCode\Service\SynchronizationResult;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

final class SynchronizationResultTest extends UnitTestCase
{
    #[Test]
    public function emptyResultHasNoChanges(): void
    {
        self::assertFalse((new SynchronizationResult())->hasChanges());
    }

    #[Test]
    public function failedTasksAloneAreNoChange(): void
    {
        $result = new SynchronizationResult();
        $result->failed['broken'] = 'reason';

        self::assertFalse($result->hasChanges());
    }

    /**
     * @return array<string, array{string}>
     */
    public static function changeDataProvider(): array
    {
        return [
            'created' => ['created'],
            'updated' => ['updated'],
            'disabled' => ['disabled'],
        ];
    }

    #[Test]
    #[DataProvider('changeDataProvider')]
    public function anyCreatedUpdatedOrDisabledTaskIsAChange(string $property): void
    {
        $result = new SynchronizationResult();
        $result->{$property}[] = 'task';

        self::assertTrue($result->hasChanges());
    }
}
