<?php

declare(strict_types=1);

namespace MaikSchneider\SchedulerAsCode\Tests\Unit\Configuration;

use MaikSchneider\SchedulerAsCode\Configuration\TaskDefinition;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

final class TaskDefinitionTest extends UnitTestCase
{
    #[Test]
    public function hashIgnoresKeyOrder(): void
    {
        $a = new TaskDefinition('task', ['command' => 'a:b', 'options' => ['x' => 1, 'y' => 2]], 'a.yaml');
        $b = new TaskDefinition('task', ['options' => ['y' => 2, 'x' => 1], 'command' => 'a:b'], 'b.yaml');

        self::assertSame($a->getHash(), $b->getHash());
    }

    #[Test]
    public function hashKeepsListOrder(): void
    {
        $a = new TaskDefinition('task', ['type' => 'T', 'parameters' => ['tables' => ['a', 'b']]], 'a.yaml');
        $b = new TaskDefinition('task', ['type' => 'T', 'parameters' => ['tables' => ['b', 'a']]], 'a.yaml');

        self::assertNotSame($a->getHash(), $b->getHash());
    }

    #[Test]
    public function hashChangesWithValue(): void
    {
        $a = new TaskDefinition('task', ['command' => 'a:b', 'frequency' => '0 3 * * *'], 'a.yaml');
        $b = new TaskDefinition('task', ['command' => 'a:b', 'frequency' => '0 4 * * *'], 'a.yaml');

        self::assertNotSame($a->getHash(), $b->getHash());
    }
}
