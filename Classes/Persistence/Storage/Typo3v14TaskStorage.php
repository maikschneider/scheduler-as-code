<?php

declare(strict_types=1);

namespace MaikSchneider\SchedulerAsCode\Persistence\Storage;

use MaikSchneider\SchedulerAsCode\Configuration\CommandParameterMapper;
use MaikSchneider\SchedulerAsCode\Configuration\ExecutionMapper;
use MaikSchneider\SchedulerAsCode\Configuration\TaskDefinition;
use MaikSchneider\SchedulerAsCode\Persistence\TaskStorageInterface;
use MaikSchneider\SchedulerAsCode\Persistence\UnknownTaskTypeException;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Scheduler\Execution;
use TYPO3\CMS\Scheduler\Service\TaskService;
use TYPO3\CMS\Scheduler\Task\ExecuteSchedulableCommandTask;

/**
 * Writes rows directly instead of going through SchedulerTaskRepository: the repository uses
 * DataHandler, which needs an admin backend user that a boot-time import does not have.
 */
final readonly class Typo3v14TaskStorage implements TaskStorageInterface
{
    private const TABLE = 'tx_scheduler_task';

    public function __construct(
        private ConnectionPool $connectionPool,
        private TaskService $taskService,
        private ExecutionMapper $executionMapper,
        private CommandParameterMapper $commandParameterMapper,
    ) {
    }

    public function write(TaskDefinition $definition, ?int $uid, int $taskGroup): int
    {
        $configuration = $definition->configuration;
        $type = (string)$configuration['type'];
        if (!$this->taskService->isTaskTypeRegistered($type)) {
            throw new UnknownTaskTypeException(
                sprintf('Task type "%s" of scheduler task "%s" is not registered.', $type, $definition->identifier),
                1791360101
            );
        }
        $details = $this->taskService->getTaskDetailsFromTaskType($type) ?? [];
        /** @var array<string, mixed> $parameters */
        $parameters = (array)($configuration['parameters'] ?? []);
        $now = (int)$GLOBALS['EXEC_TIME'];

        $execution = $this->executionMapper->toExecution((array)($configuration['execution'] ?? []), $now);
        $disabled = (bool)($configuration['disabled'] ?? false);
        try {
            $nextExecution = (int)$execution->getNextExecution();
        } catch (\Exception) {
            $nextExecution = 0;
            $disabled = true;
        }

        $fields = [
            'tasktype' => $type,
            'description' => (string)($configuration['description'] ?? ''),
            'task_group' => $taskGroup,
            'disable' => (int)$disabled,
            'priority' => (int)($configuration['priority'] ?? 100),
            // JSON columns: Connection encodes arrays itself.
            'execution_details' => $execution->toArray(),
            'nextexecution' => $nextExecution,
            'deleted' => 0,
        ];

        if (is_a((string)($details['className'] ?? ''), ExecuteSchedulableCommandTask::class, true)) {
            $parameters = $this->commandParameterMapper->toCore($type, $parameters);
        } elseif ($details['isNativeTask'] ?? false) {
            foreach ((array)($details['additionalFields'] ?? []) as $field) {
                if (array_key_exists($field, $parameters)) {
                    $value = $parameters[$field];
                    $fields[$field] = is_array($value) ? implode(',', $value) : $value;
                    unset($parameters[$field]);
                }
            }
        }
        $fields['parameters'] = $parameters;

        $connection = $this->connectionPool->getConnectionForTable(self::TABLE);
        if ($uid === null) {
            $connection->insert(self::TABLE, $fields + ['pid' => 0, 'crdate' => $now]);
            return (int)$connection->lastInsertId();
        }
        $connection->update(self::TABLE, $fields, ['uid' => $uid]);
        return $uid;
    }

    public function read(array $row): array
    {
        $type = (string)($row['tasktype'] ?? '');
        $details = $this->taskService->isTaskTypeRegistered($type) ? ($this->taskService->getTaskDetailsFromTaskType($type) ?? []) : [];
        $stored = json_decode((string)($row['parameters'] ?? ''), true);
        $stored = is_array($stored) ? $stored : [];

        if (is_a((string)($details['className'] ?? ''), ExecuteSchedulableCommandTask::class, true)) {
            $parameters = $this->commandParameterMapper->fromCore(
                (array)($stored['arguments'] ?? []),
                (array)($stored['options'] ?? []),
                (array)($stored['optionValues'] ?? [])
            );
        } else {
            $parameters = $stored;
            foreach ((array)($details['additionalFields'] ?? []) as $field) {
                if (array_key_exists($field, $row)) {
                    $parameters[$field] = $this->isMultiValueField($field)
                        ? array_values(array_filter(explode(',', (string)$row[$field]), static fn (string $v): bool => $v !== ''))
                        : $row[$field];
                }
            }
        }

        $executionDetails = json_decode((string)($row['execution_details'] ?? ''), true);
        $configuration = [
            'type' => $type,
            'description' => (string)($row['description'] ?? ''),
            'disabled' => (bool)($row['disable'] ?? false),
            'priority' => (int)($row['priority'] ?? 100),
            'execution' => $this->executionMapper->fromExecution(
                Execution::createFromDetails(is_array($executionDetails) ? $executionDetails : [])
            ),
            'parameters' => $parameters,
        ];
        return $configuration;
    }

    private function isMultiValueField(string $field): bool
    {
        $config = $GLOBALS['TCA'][self::TABLE]['columns'][$field]['config'] ?? [];
        return ($config['type'] ?? '') === 'select'
            && (str_starts_with((string)($config['renderType'] ?? ''), 'selectMultiple') || (int)($config['maxitems'] ?? 1) > 1);
    }
}
