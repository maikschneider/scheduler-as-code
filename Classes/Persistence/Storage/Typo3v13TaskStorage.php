<?php

declare(strict_types=1);

namespace MaikSchneider\SchedulerAsCode\Persistence\Storage;

use MaikSchneider\SchedulerAsCode\Configuration\CommandParameterMapper;
use MaikSchneider\SchedulerAsCode\Configuration\ExecutionMapper;
use MaikSchneider\SchedulerAsCode\Configuration\TaskDefinition;
use MaikSchneider\SchedulerAsCode\Persistence\TaskStorageInterface;
use MaikSchneider\SchedulerAsCode\Persistence\UnknownTaskTypeException;
use TYPO3\CMS\Core\Console\CommandRegistry;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Scheduler\Domain\Repository\SchedulerTaskRepository;
use TYPO3\CMS\Scheduler\Execution;
use TYPO3\CMS\Scheduler\Task\AbstractTask;
use TYPO3\CMS\Scheduler\Task\ExecuteSchedulableCommandTask;

/**
 * TYPO3 13 stores each task as a serialized object. The core repository writes it without
 * DataHandler, so it is safe to use during boot. Task parameters map to the properties of the
 * task class; for console commands those are the same keys TYPO3 14 keeps in "parameters".
 */
final readonly class Typo3v13TaskStorage implements TaskStorageInterface
{
    private const TABLE = 'tx_scheduler_task';

    public function __construct(
        private ConnectionPool $connectionPool,
        private SchedulerTaskRepository $taskRepository,
        private CommandRegistry $commandRegistry,
        private ExecutionMapper $executionMapper,
        private CommandParameterMapper $commandParameterMapper,
    ) {
    }

    public function write(TaskDefinition $definition, ?int $uid, int $taskGroup): int
    {
        $configuration = $definition->configuration;
        $type = (string)$configuration['type'];
        /** @var array<string, mixed> $parameters */
        $parameters = (array)($configuration['parameters'] ?? []);

        if ($this->commandRegistry->has($type)) {
            $task = GeneralUtility::makeInstance(ExecuteSchedulableCommandTask::class);
            $parameters = $this->commandParameterMapper->toCore($type, $parameters);
        } elseif (class_exists($type) && is_subclass_of($type, AbstractTask::class)) {
            /** @var AbstractTask $task */
            $task = GeneralUtility::makeInstance($type);
        } else {
            throw new UnknownTaskTypeException(
                sprintf('Task type "%s" of scheduler task "%s" is neither a console command nor a task class.', $type, $definition->identifier),
                1791360102
            );
        }

        foreach ($parameters as $name => $value) {
            $this->setProperty($task, (string)$name, $value);
        }
        $task->setDescription((string)($configuration['description'] ?? ''));
        $task->setTaskGroup($taskGroup);
        $task->setDisabled((bool)($configuration['disabled'] ?? false));
        $task->setExecution($this->executionMapper->toExecution(
            (array)($configuration['execution'] ?? []),
            (int)$GLOBALS['EXEC_TIME']
        ));

        if ($uid === null) {
            $this->taskRepository->add($task);
            return (int)$task->getTaskUid();
        }
        $task->setTaskUid($uid);
        $this->taskRepository->update($task);
        $this->connectionPool->getConnectionForTable(self::TABLE)->update(self::TABLE, ['deleted' => 0], ['uid' => $uid]);
        return $uid;
    }

    public function read(array $row): array
    {
        $task = @unserialize((string)($row['serialized_task_object'] ?? ''));
        if (!$task instanceof AbstractTask) {
            throw new UnknownTaskTypeException(
                sprintf('Scheduler task %d cannot be restored; its class is probably no longer available.', (int)($row['uid'] ?? 0)),
                1791360103
            );
        }

        if ($task instanceof ExecuteSchedulableCommandTask) {
            $type = (string)$this->getProperty($task, 'commandIdentifier');
            $parameters = $this->commandParameterMapper->fromCore(
                (array)$this->getProperty($task, 'arguments'),
                (array)$this->getProperty($task, 'options'),
                (array)$this->getProperty($task, 'optionValues')
            );
        } else {
            $type = $task::class;
            $parameters = $this->getTaskSpecificProperties($task);
        }

        $execution = $task->getExecution();
        return [
            'type' => $type,
            'description' => (string)($row['description'] ?? ''),
            'disabled' => (bool)($row['disable'] ?? false),
            'execution' => $execution instanceof Execution ? $this->executionMapper->fromExecution($execution) : [],
            'parameters' => $parameters,
        ];
    }

    /**
     * Properties declared below AbstractTask, i.e. the settings of this particular task type.
     *
     * @return array<string, mixed>
     */
    private function getTaskSpecificProperties(AbstractTask $task): array
    {
        $properties = [];
        foreach ((new \ReflectionObject($task))->getProperties() as $property) {
            $declaringClass = $property->getDeclaringClass()->getName();
            if ($property->isStatic() || $declaringClass === AbstractTask::class || !is_subclass_of($declaringClass, AbstractTask::class)) {
                continue;
            }
            if (!$property->isInitialized($task)) {
                continue;
            }
            $value = $property->getValue($task);
            if (is_scalar($value) || is_array($value) || $value === null) {
                $properties[$property->getName()] = $value;
            }
        }
        return $properties;
    }

    private function setProperty(AbstractTask $task, string $name, mixed $value): void
    {
        $reflection = new \ReflectionObject($task);
        if (!$reflection->hasProperty($name)) {
            throw new UnknownTaskTypeException(
                sprintf('Task class "%s" has no parameter "%s".', $task::class, $name),
                1791360104
            );
        }
        $reflection->getProperty($name)->setValue($task, $value);
    }

    private function getProperty(AbstractTask $task, string $name): mixed
    {
        $reflection = new \ReflectionObject($task);
        return $reflection->hasProperty($name) ? $reflection->getProperty($name)->getValue($task) : null;
    }
}
