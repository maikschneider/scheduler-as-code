<?php

declare(strict_types=1);

namespace MaikSchneider\SchedulerAsCode\Persistence;

use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;

/**
 * The link between task records and task files: identifier, content hash and import time,
 * stored in columns this extension adds to tx_scheduler_task.
 */
class ManagedTaskRepository
{
    public const TABLE = 'tx_scheduler_task';
    private const GROUP_TABLE = 'tx_scheduler_task_group';

    public function __construct(
        private readonly ConnectionPool $connectionPool,
    ) {
    }

    /**
     * Includes deleted records, so that a task deleted in the backend comes back while its file
     * still exists.
     *
     * @return array<string, array{uid: int, deleted: int, hash: string}> keyed by identifier
     */
    public function findManaged(): array
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable(self::TABLE);
        $queryBuilder->getRestrictions()->removeAll();
        $rows = $queryBuilder
            ->select('uid', 'deleted', 'tx_schedulerascode_identifier', 'tx_schedulerascode_hash')
            ->from(self::TABLE)
            ->where($queryBuilder->expr()->neq('tx_schedulerascode_identifier', $queryBuilder->createNamedParameter('')))
            ->orderBy('uid')
            ->executeQuery()
            ->fetchAllAssociative();

        $managed = [];
        foreach ($rows as $row) {
            $managed[(string)$row['tx_schedulerascode_identifier']] ??= [
                'uid' => (int)$row['uid'],
                'deleted' => (int)$row['deleted'],
                'hash' => (string)$row['tx_schedulerascode_hash'],
            ];
        }
        return $managed;
    }

    /**
     * @param list<int> $uids empty for all tasks
     * @return list<array<string, mixed>>
     */
    public function findTasks(array $uids = []): array
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable(self::TABLE);
        $queryBuilder->getRestrictions()->removeAll();
        $queryBuilder
            ->select('*')
            ->from(self::TABLE)
            ->where($queryBuilder->expr()->eq('deleted', 0))
            ->orderBy('uid');
        if ($uids !== []) {
            $queryBuilder->andWhere($queryBuilder->expr()->in('uid', $queryBuilder->createNamedParameter($uids, Connection::PARAM_INT_ARRAY)));
        }
        /** @var list<array<string, mixed>> */
        return $queryBuilder->executeQuery()->fetchAllAssociative();
    }

    /**
     * @return array<int, array{identifier: string, orphaned: bool}> keyed by uid
     */
    public function findStates(): array
    {
        $states = [];
        foreach ($this->findManaged() as $identifier => $task) {
            $states[$task['uid']] = ['identifier' => $identifier, 'orphaned' => $task['hash'] === ''];
        }
        return $states;
    }

    public function markManaged(int $uid, string $identifier, string $hash): void
    {
        $this->connectionPool->getConnectionForTable(self::TABLE)->update(
            self::TABLE,
            [
                'tx_schedulerascode_identifier' => $identifier,
                'tx_schedulerascode_hash' => $hash,
                'tx_schedulerascode_imported' => (int)$GLOBALS['EXEC_TIME'],
            ],
            ['uid' => $uid]
        );
    }

    /**
     * The identifier stays, so the backend can still tell where the task came from. Clearing
     * the hash makes the task import again should its file come back.
     */
    public function markOrphaned(int $uid): void
    {
        $this->connectionPool->getConnectionForTable(self::TABLE)->update(
            self::TABLE,
            ['disable' => 1, 'tx_schedulerascode_hash' => ''],
            ['uid' => $uid]
        );
    }

    public function findOrCreateGroup(string $name): int
    {
        if ($name === '') {
            return 0;
        }
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable(self::GROUP_TABLE);
        $queryBuilder->getRestrictions()->removeAll();
        $uid = $queryBuilder
            ->select('uid')
            ->from(self::GROUP_TABLE)
            ->where(
                $queryBuilder->expr()->eq('groupName', $queryBuilder->createNamedParameter($name)),
                $queryBuilder->expr()->eq('deleted', 0)
            )
            ->orderBy('uid')
            ->setMaxResults(1)
            ->executeQuery()
            ->fetchOne();
        if ($uid !== false) {
            return (int)$uid;
        }

        $ctrl = $GLOBALS['TCA'][self::GROUP_TABLE]['ctrl'] ?? [];
        $fields = ['pid' => 0, 'groupName' => $name];
        foreach (['crdate', 'tstamp'] as $timestampField) {
            if (isset($ctrl[$timestampField])) {
                $fields[$ctrl[$timestampField]] = (int)$GLOBALS['EXEC_TIME'];
            }
        }
        $connection = $this->connectionPool->getConnectionForTable(self::GROUP_TABLE);
        $connection->insert(self::GROUP_TABLE, $fields);
        return (int)$connection->lastInsertId();
    }

    public function findGroupName(int $uid): string
    {
        if ($uid === 0) {
            return '';
        }
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable(self::GROUP_TABLE);
        $queryBuilder->getRestrictions()->removeAll();
        $name = $queryBuilder
            ->select('groupName')
            ->from(self::GROUP_TABLE)
            ->where(
                $queryBuilder->expr()->eq('uid', $queryBuilder->createNamedParameter($uid, Connection::PARAM_INT)),
                $queryBuilder->expr()->eq('deleted', 0)
            )
            ->executeQuery()
            ->fetchOne();
        return $name === false ? '' : (string)$name;
    }
}
