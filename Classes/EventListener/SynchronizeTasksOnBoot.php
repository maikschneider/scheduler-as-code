<?php

declare(strict_types=1);

namespace MaikSchneider\SchedulerAsCode\EventListener;

use MaikSchneider\SchedulerAsCode\Configuration\InvalidTaskDefinitionException;
use MaikSchneider\SchedulerAsCode\Configuration\TaskDefinitionProvider;
use MaikSchneider\SchedulerAsCode\Service\SynchronizationResult;
use MaikSchneider\SchedulerAsCode\Service\TaskSynchronizer;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use TYPO3\CMS\Core\Attribute\AsEventListener;
use TYPO3\CMS\Core\Cache\Frontend\FrontendInterface;
use TYPO3\CMS\Core\Core\Event\BootCompletedEvent;
use TYPO3\CMS\Core\Locking\Exception\LockAcquireWouldBlockException;
use TYPO3\CMS\Core\Locking\LockFactory;
use TYPO3\CMS\Core\Locking\LockingStrategyInterface;

/**
 * Imports task files whenever they changed, or the caches were flushed. Runs on every boot, so
 * the common path is one stat per task file and one cache read.
 */
#[AsEventListener(identifier: 'scheduler-as-code/synchronize-tasks')]
final readonly class SynchronizeTasksOnBoot
{
    private const CACHE_ENTRY = 'fingerprint';

    public function __construct(
        private TaskDefinitionProvider $definitionProvider,
        private TaskSynchronizer $synchronizer,
        #[Autowire(service: 'cache.scheduler_as_code')]
        private FrontendInterface $cache,
        private LockFactory $lockFactory,
        private LoggerInterface $logger,
    ) {
    }

    public function __invoke(BootCompletedEvent $event): void
    {
        $fingerprint = $this->definitionProvider->getFingerprint();
        if ($this->cache->get(self::CACHE_ENTRY) === $fingerprint) {
            return;
        }

        $locker = $this->lockFactory->createLocker(
            'scheduler_as_code',
            LockingStrategyInterface::LOCK_CAPABILITY_EXCLUSIVE | LockingStrategyInterface::LOCK_CAPABILITY_NOBLOCK
        );
        try {
            if (!$locker->acquire(LockingStrategyInterface::LOCK_CAPABILITY_EXCLUSIVE | LockingStrategyInterface::LOCK_CAPABILITY_NOBLOCK)) {
                return;
            }
        } catch (LockAcquireWouldBlockException) {
            return;
        }

        try {
            if ($this->cache->get(self::CACHE_ENTRY) === $fingerprint) {
                return;
            }
            $this->log($this->synchronizer->synchronize($this->definitionProvider->getDefinitions()));
            $this->cache->set(self::CACHE_ENTRY, $fingerprint);
        } catch (InvalidTaskDefinitionException $e) {
            // A broken file stays broken until it changes, so remember the fingerprint instead
            // of failing again on every request.
            $this->logger->error('Scheduler task files were not imported: {message}', ['message' => $e->getMessage()]);
            $this->cache->set(self::CACHE_ENTRY, $fingerprint);
        } catch (\Throwable $e) {
            // Typically the database schema is not updated yet during a deployment. Retried on
            // the next boot.
            $this->logger->warning('Scheduler task files could not be imported yet: {message}', ['message' => $e->getMessage()]);
        } finally {
            $locker->release();
        }
    }

    private function log(SynchronizationResult $result): void
    {
        if ($result->hasChanges()) {
            $this->logger->info('Scheduler task files imported.', [
                'created' => $result->created,
                'updated' => $result->updated,
                'disabled' => $result->disabled,
            ]);
        }
        foreach ($result->failed as $identifier => $reason) {
            $this->logger->error('Scheduler task "{identifier}" was not imported: {reason}', ['identifier' => $identifier, 'reason' => $reason]);
        }
    }
}
