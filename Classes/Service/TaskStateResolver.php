<?php

declare(strict_types=1);

namespace MaikSchneider\SchedulerAsCode\Service;

use MaikSchneider\SchedulerAsCode\Persistence\ManagedTaskRepository;
use MaikSchneider\SchedulerAsCode\Persistence\UnknownTaskTypeException;

/**
 * How each file-managed task relates to its file: in sync, changed in the database since the
 * last import or export ("stale"), or left behind by a removed file ("orphaned").
 */
class TaskStateResolver
{
    public function __construct(
        private readonly ManagedTaskRepository $managedTaskRepository,
        private readonly TaskSnapshot $taskSnapshot,
    ) {
    }

    /**
     * @return array<int, array{identifier: string, source: string, orphaned: bool, stale: bool}> keyed by uid
     */
    public function getStates(): array
    {
        $states = [];
        foreach ($this->managedTaskRepository->findManagedTasks() as $row) {
            $orphaned = (string)$row['tx_schedulerascode_hash'] === '';
            $states[(int)$row['uid']] = [
                'identifier' => (string)$row['tx_schedulerascode_identifier'],
                'source' => (string)$row['tx_schedulerascode_source'],
                'orphaned' => $orphaned,
                'stale' => !$orphaned && $this->isStale($row),
            ];
        }
        return $states;
    }

    /**
     * @param array<string, mixed> $row
     */
    public function isStale(array $row): bool
    {
        $recordHash = (string)($row['tx_schedulerascode_record_hash'] ?? '');
        if ($recordHash === '') {
            // Linked before stale detection existed; nothing to compare with yet.
            return false;
        }
        try {
            return $this->taskSnapshot->getHash($row) !== $recordHash;
        } catch (UnknownTaskTypeException) {
            return false;
        }
    }
}
