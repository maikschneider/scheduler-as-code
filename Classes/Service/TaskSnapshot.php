<?php

declare(strict_types=1);

namespace MaikSchneider\SchedulerAsCode\Service;

use MaikSchneider\SchedulerAsCode\Configuration\TaskDefinition;
use MaikSchneider\SchedulerAsCode\Persistence\ManagedTaskRepository;
use MaikSchneider\SchedulerAsCode\Persistence\TaskStorageInterface;

/**
 * A task record in the task file format. Its hash, taken right after an import or export,
 * tells later whether someone changed the record since.
 */
class TaskSnapshot
{
    public function __construct(
        private readonly TaskStorageInterface $taskStorage,
        private readonly ManagedTaskRepository $managedTaskRepository,
    ) {
    }

    /**
     * Fixed key order and no defaults, so files stay short and diffs stay readable.
     *
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    public function getConfiguration(array $row): array
    {
        $configuration = $this->taskStorage->read($row);
        $normalized = ['type' => $configuration['type']];
        if (($configuration['description'] ?? '') !== '') {
            $normalized['description'] = $configuration['description'];
        }
        $group = $this->managedTaskRepository->findGroupName((int)($row['task_group'] ?? 0));
        if ($group !== '') {
            $normalized['group'] = $group;
        }
        if ($configuration['disabled'] ?? false) {
            $normalized['disabled'] = true;
        }
        if (isset($configuration['priority']) && (int)$configuration['priority'] !== 100) {
            $normalized['priority'] = (int)$configuration['priority'];
        }
        $normalized['execution'] = $configuration['execution'];
        if (($configuration['parameters'] ?? []) !== []) {
            $normalized['parameters'] = $configuration['parameters'];
        }
        return $normalized;
    }

    /**
     * @param array<string, mixed> $row
     */
    public function getHash(array $row): string
    {
        $configuration = $this->getConfiguration($row);
        // The scheduler disables a single-run task when it runs; that is not an edit. Every run
        // records lastexecution_time, so a disable without one came from someone else.
        if (!isset($configuration['execution']['frequency']) && (int)($row['lastexecution_time'] ?? 0) > 0) {
            unset($configuration['disabled']);
        }
        return (new TaskDefinition('', $configuration, ''))->getHash();
    }
}
