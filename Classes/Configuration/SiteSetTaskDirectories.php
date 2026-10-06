<?php

declare(strict_types=1);

namespace MaikSchneider\SchedulerAsCode\Configuration;

use Symfony\Component\Yaml\Yaml;
use TYPO3\CMS\Core\Package\PackageManager;
use TYPO3\CMS\Core\Site\Set\SetRegistry;
use TYPO3\CMS\Core\Site\SiteFinder;

/**
 * Finds the task files shipped with site sets: Configuration/Sets/<Set>/scheduler/*.yaml.
 * A set's tasks only count while at least one site uses the set, directly or as a dependency.
 */
class SiteSetTaskDirectories
{
    public function __construct(
        private readonly PackageManager $packageManager,
        private readonly SiteFinder $siteFinder,
        private readonly SetRegistry $setRegistry,
    ) {
    }

    /**
     * @return array<string, string> set name => directory, in set dependency order
     */
    public function getDirectories(): array
    {
        $available = $this->findSetsWithTasks();
        if ($available === []) {
            return [];
        }

        $directories = [];
        foreach ($this->getActiveSetNames() as $setName) {
            if (isset($available[$setName])) {
                $directories[$setName] = $available[$setName];
            }
        }
        return $directories;
    }

    /**
     * @return list<string>
     */
    private function getActiveSetNames(): array
    {
        $used = [];
        foreach ($this->siteFinder->getAllSites() as $site) {
            foreach ($site->getSets() as $setName) {
                $used[$setName] = true;
            }
        }
        if ($used === []) {
            return [];
        }
        return array_map(
            static fn ($set): string => $set->name,
            $this->setRegistry->getSets(...array_keys($used))
        );
    }

    /**
     * One glob per package; config.yaml is only read for sets that ship tasks.
     *
     * @return array<string, string> set name => directory
     */
    private function findSetsWithTasks(): array
    {
        $sets = [];
        foreach ($this->packageManager->getActivePackages() as $package) {
            foreach (glob($package->getPackagePath() . 'Configuration/Sets/*/scheduler', GLOB_ONLYDIR) ?: [] as $directory) {
                $configFile = dirname($directory) . '/config.yaml';
                if (!is_file($configFile)) {
                    continue;
                }
                $configuration = Yaml::parseFile($configFile);
                if (is_array($configuration) && is_string($configuration['name'] ?? null)) {
                    $sets[$configuration['name']] = $directory;
                }
            }
        }
        return $sets;
    }
}
