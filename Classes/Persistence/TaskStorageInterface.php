<?php

declare(strict_types=1);

namespace MaikSchneider\SchedulerAsCode\Persistence;

use MaikSchneider\SchedulerAsCode\Configuration\TaskDefinition;

/**
 * Writes task files into tx_scheduler_task and reads records back into the file format.
 * TYPO3 13 keeps tasks as serialized objects, TYPO3 14 as TCA records, so each major
 * version has its own implementation.
 */
interface TaskStorageInterface
{
    /**
     * @param int|null $uid the record to overwrite, or null to create one
     * @return int uid of the written record
     * @throws UnknownTaskTypeException
     */
    public function write(TaskDefinition $definition, ?int $uid, int $taskGroup): int;

    /**
     * @param array<string, mixed> $row a tx_scheduler_task row
     * @return array<string, mixed> type, description, disabled, execution, parameters and,
     *                              where supported, priority. The group is handled by the caller.
     */
    public function read(array $row): array;
}
