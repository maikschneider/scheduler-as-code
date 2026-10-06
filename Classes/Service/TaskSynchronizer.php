<?php

declare(strict_types=1);

namespace MaikSchneider\SchedulerAsCode\Service;

use MaikSchneider\SchedulerAsCode\Configuration\InvalidTaskDefinitionException;
use MaikSchneider\SchedulerAsCode\Configuration\TaskDefinition;
use MaikSchneider\SchedulerAsCode\Persistence\ManagedTaskRepository;
use MaikSchneider\SchedulerAsCode\Persistence\TaskStorageInterface;
use MaikSchneider\SchedulerAsCode\Persistence\UnknownTaskTypeException;

/**
 * Brings tx_scheduler_task in line with the task files. Files are the source of truth: a new
 * file creates a task, a changed file overwrites it, a removed file disables it.
 */
class TaskSynchronizer
{
    public function __construct(
        private readonly ManagedTaskRepository $managedTaskRepository,
        private readonly TaskStorageInterface $taskStorage,
    ) {
    }

    /**
     * @param array<string, TaskDefinition> $definitions keyed by identifier
     */
    public function synchronize(array $definitions): SynchronizationResult
    {
        $result = new SynchronizationResult();
        $managed = $this->managedTaskRepository->findManaged();

        foreach ($definitions as $identifier => $definition) {
            $existing = $managed[$identifier] ?? null;
            $hash = $definition->getHash();
            if ($existing !== null && $existing['hash'] === $hash && $existing['deleted'] === 0) {
                // Same content from another file, e.g. a project file overriding a set's task.
                if ($existing['source'] !== $this->managedTaskRepository->relativeToProject($definition->sourceFile)) {
                    $this->managedTaskRepository->markManaged($existing['uid'], $identifier, $hash, $definition->sourceFile);
                }
                continue;
            }
            try {
                $group = $this->managedTaskRepository->findOrCreateGroup((string)($definition->configuration['group'] ?? ''));
                $uid = $this->taskStorage->write($definition, $existing['uid'] ?? null, $group);
            } catch (UnknownTaskTypeException|InvalidTaskDefinitionException $e) {
                $result->failed[$identifier] = $e->getMessage();
                continue;
            }
            $this->managedTaskRepository->markManaged($uid, $identifier, $hash, $definition->sourceFile);
            if ($existing === null) {
                $result->created[] = $identifier;
            } else {
                $result->updated[] = $identifier;
            }
        }

        foreach ($managed as $identifier => $task) {
            if (!isset($definitions[$identifier]) && $task['hash'] !== '' && $task['deleted'] === 0) {
                $this->managedTaskRepository->markOrphaned($task['uid']);
                $result->disabled[] = $identifier;
            }
        }
        return $result;
    }
}
