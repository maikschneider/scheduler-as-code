<?php

declare(strict_types=1);

namespace MaikSchneider\SchedulerAsCode\Tests\Unit\Configuration;

use MaikSchneider\SchedulerAsCode\Configuration\CommandParameterMapper;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

final class CommandParameterMapperTest extends UnitTestCase
{
    #[Test]
    public function flagsAndValuedOptionsAreSplitIntoTheCoreMaps(): void
    {
        $core = (new CommandParameterMapper())->toCore('cleanup:deletedrecords', [
            'arguments' => ['table' => 'sys_log'],
            'options' => ['min-age' => 30, 'dry-run' => true, 'pid' => 0],
        ]);

        self::assertSame([
            'commandIdentifier' => 'cleanup:deletedrecords',
            'arguments' => ['table' => 'sys_log'],
            'options' => ['min-age' => true, 'dry-run' => true, 'pid' => true],
            'optionValues' => ['min-age' => 30, 'pid' => 0],
        ], $core);
    }

    #[Test]
    public function falseAndNullOptionsAreLeftOut(): void
    {
        $core = (new CommandParameterMapper())->toCore('a:b', ['options' => ['dry-run' => false, 'depth' => null]]);

        self::assertSame([], $core['options']);
        self::assertSame([], $core['optionValues']);
    }

    #[Test]
    public function missingParametersGiveEmptyMaps(): void
    {
        self::assertSame(
            ['commandIdentifier' => 'a:b', 'arguments' => [], 'options' => [], 'optionValues' => []],
            (new CommandParameterMapper())->toCore('a:b', [])
        );
    }

    #[Test]
    public function readingBackKeepsEnabledOptionsAndDropsEmptyArguments(): void
    {
        $parameters = (new CommandParameterMapper())->fromCore(
            ['table' => 'sys_log', 'empty' => '', 'none' => null, 'list' => []],
            ['min-age' => true, 'dry-run' => true, 'verbose' => false, 'pid' => '1'],
            ['min-age' => 30, 'dry-run' => '', 'verbose' => 'ignored', 'pid' => 0]
        );

        self::assertSame([
            'arguments' => ['table' => 'sys_log'],
            'options' => ['min-age' => 30, 'dry-run' => true, 'pid' => 0],
        ], $parameters);
    }

    #[Test]
    public function commandWithoutParametersReadsBackAsEmpty(): void
    {
        self::assertSame([], (new CommandParameterMapper())->fromCore([], ['dry-run' => false], []));
    }

    #[Test]
    public function parametersSurviveTheRoundTrip(): void
    {
        $mapper = new CommandParameterMapper();
        $parameters = ['arguments' => ['table' => 'sys_log'], 'options' => ['min-age' => 30, 'dry-run' => true]];

        $core = $mapper->toCore('a:b', $parameters);

        self::assertSame($parameters, $mapper->fromCore($core['arguments'], $core['options'], $core['optionValues']));
    }
}
