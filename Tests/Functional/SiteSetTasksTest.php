<?php

declare(strict_types=1);

namespace MaikSchneider\SchedulerAsCode\Tests\Functional;

use MaikSchneider\SchedulerAsCode\Configuration\InvalidTaskDefinitionException;
use MaikSchneider\SchedulerAsCode\Configuration\TaskDefinitionProvider;
use MaikSchneider\SchedulerAsCode\Service\SynchronizationResult;
use MaikSchneider\SchedulerAsCode\Service\TaskStateResolver;
use MaikSchneider\SchedulerAsCode\Service\TaskSynchronizer;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\Yaml\Yaml;
use TYPO3\CMS\Core\Cache\CacheManager;
use TYPO3\CMS\Core\Core\Environment;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

final class SiteSetTasksTest extends FunctionalTestCase
{
    private const SET_PREFIX = 'maikschneider/scheduler-as-code-fixture-';

    protected array $coreExtensionsToLoad = ['scheduler', 'lowlevel'];
    protected array $testExtensionsToLoad = [
        'maikschneider/scheduler-as-code',
        'maikschneider/scheduler-as-code-fixture',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $projectDirectory = $this->get(TaskDefinitionProvider::class)->getDirectory();
        @mkdir($projectDirectory, 0777, true);
        array_map('unlink', glob($projectDirectory . '/*.yaml') ?: []);
    }

    #[Test]
    public function setTasksAreIgnoredWithoutASiteUsingTheSet(): void
    {
        $this->writeSite([]);

        self::assertSame([], $this->synchronize()->created);
    }

    #[Test]
    public function tasksOfUsedSetsAndTheirDependenciesAreImported(): void
    {
        $this->writeSite(['maintenance']);

        $result = $this->synchronize();

        self::assertEqualsCanonicalizing(['base-cleanup', 'nightly-cleanup'], $result->created);
        self::assertStringEndsWith(
            'scheduler_as_code_fixture/Configuration/Sets/Maintenance/scheduler/nightly-cleanup.yaml',
            $this->findRow('nightly-cleanup')['tx_schedulerascode_source']
        );
    }

    #[Test]
    public function projectFileOverridesTheSetsTask(): void
    {
        $this->writeSite(['maintenance']);
        $this->synchronize();

        file_put_contents(
            $this->get(TaskDefinitionProvider::class)->getDirectory() . '/nightly-cleanup.yaml',
            "type: 'cleanup:deletedrecords'\ndescription: 'from project'\nexecution:\n  frequency: '0 4 * * *'\n"
        );
        $result = $this->synchronize();

        self::assertSame(['nightly-cleanup'], $result->updated);
        $row = $this->findRow('nightly-cleanup');
        self::assertSame('from project', $row['description']);
        self::assertStringEndsWith('/scheduler/nightly-cleanup.yaml', $row['tx_schedulerascode_source']);
        self::assertStringNotContainsString('Sets/', $row['tx_schedulerascode_source']);
    }

    #[Test]
    public function projectFileWithTheSetsContentTakesOverWithoutBecomingStale(): void
    {
        $this->writeSite(['maintenance']);
        $this->synchronize();
        $row = $this->findRow('nightly-cleanup');
        $recordHash = $row['tx_schedulerascode_record_hash'];
        self::assertNotSame('', $recordHash);
        copy(
            Environment::getProjectPath() . '/' . $row['tx_schedulerascode_source'],
            $this->get(TaskDefinitionProvider::class)->getDirectory() . '/nightly-cleanup.yaml'
        );

        $result = $this->synchronize();

        self::assertFalse($result->hasChanges());
        $row = $this->findRow('nightly-cleanup');
        self::assertStringNotContainsString('Sets/', $row['tx_schedulerascode_source']);
        self::assertSame($recordHash, $row['tx_schedulerascode_record_hash']);
        self::assertFalse($this->get(TaskStateResolver::class)->getStates()[(int)$row['uid']]['stale']);
    }

    #[Test]
    public function removingTheSetFromTheSiteDisablesItsTasks(): void
    {
        $this->writeSite(['maintenance']);
        $this->synchronize();

        $this->writeSite([]);
        $result = $this->synchronize();

        self::assertEqualsCanonicalizing(['base-cleanup', 'nightly-cleanup'], $result->disabled);
        self::assertSame(1, (int)$this->findRow('nightly-cleanup')['disable']);
    }

    #[Test]
    public function addingASetToASiteChangesTheFingerprint(): void
    {
        $provider = $this->get(TaskDefinitionProvider::class);
        $this->writeSite([]);
        $before = $provider->getFingerprint();

        $this->writeSite(['maintenance']);

        self::assertNotSame($before, $provider->getFingerprint());
    }

    #[Test]
    public function sameIdentifierInTwoSetsIsRejected(): void
    {
        $this->writeSite(['maintenance', 'conflicting']);

        $this->expectException(InvalidTaskDefinitionException::class);
        $this->expectExceptionCode(1791360009);

        $this->get(TaskDefinitionProvider::class)->getDefinitions();
    }

    #[Test]
    public function projectFileResolvesAConflictBetweenSets(): void
    {
        $this->writeSite(['maintenance', 'conflicting']);
        file_put_contents(
            $this->get(TaskDefinitionProvider::class)->getDirectory() . '/nightly-cleanup.yaml',
            "type: 'cleanup:deletedrecords'\ndescription: 'from project'\nexecution:\n  frequency: '0 4 * * *'\n"
        );

        $definitions = $this->get(TaskDefinitionProvider::class)->getDefinitions();

        self::assertSame('from project', $definitions['nightly-cleanup']->configuration['description']);
    }

    /**
     * @param list<string> $sets set names without the fixture prefix
     */
    private function writeSite(array $sets): void
    {
        $directory = Environment::getConfigPath() . '/sites/main';
        @mkdir($directory, 0777, true);
        file_put_contents($directory . '/config.yaml', Yaml::dump([
            'rootPageId' => 1,
            'base' => '/',
            'languages' => [['languageId' => 0, 'title' => 'English', 'locale' => 'en_US.UTF-8', 'base' => '/']],
            'dependencies' => array_map(static fn (string $set): string => self::SET_PREFIX . $set, $sets),
        ], 4));
        $this->get(CacheManager::class)->flushCaches();
    }

    private function synchronize(): SynchronizationResult
    {
        return $this->get(TaskSynchronizer::class)->synchronize($this->get(TaskDefinitionProvider::class)->getDefinitions());
    }

    /**
     * @return array<string, mixed>
     */
    private function findRow(string $identifier): array
    {
        $queryBuilder = $this->get(ConnectionPool::class)->getQueryBuilderForTable('tx_scheduler_task');
        $queryBuilder->getRestrictions()->removeAll();
        $row = $queryBuilder->select('*')->from('tx_scheduler_task')
            ->where($queryBuilder->expr()->eq('tx_schedulerascode_identifier', $queryBuilder->createNamedParameter($identifier)))
            ->executeQuery()->fetchAssociative();
        self::assertIsArray($row, 'No task linked to "' . $identifier . '"');
        return $row;
    }
}
