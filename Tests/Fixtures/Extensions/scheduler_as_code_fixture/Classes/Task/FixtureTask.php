<?php

declare(strict_types=1);

namespace MaikSchneider\SchedulerAsCodeFixture\Task;

use TYPO3\CMS\Scheduler\Task\AbstractTask;

/**
 * A task class whose properties are not all plain settings.
 */
class FixtureTask extends AbstractTask
{
    public static string $instances = '';

    public string $label = '';

    public string $neverSet;

    public ?\DateTimeImmutable $lastSeen = null;

    public function execute(): bool
    {
        return true;
    }
}
