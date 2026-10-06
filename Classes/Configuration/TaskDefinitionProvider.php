<?php

declare(strict_types=1);

namespace MaikSchneider\SchedulerAsCode\Configuration;

use TYPO3\CMS\Core\Core\Environment;

/**
 * Knows where task files live and whether they changed since the last look.
 *
 * Tasks come from the site sets in use and from config/scheduler/. A project file overrides
 * a set's task with the same identifier, so a project can adjust what a set ships.
 */
class TaskDefinitionProvider
{
    public function __construct(
        private readonly TaskDefinitionLoader $loader,
        private readonly SiteSetTaskDirectories $siteSetTaskDirectories,
    ) {
    }

    /**
     * The project directory; this is where scheduler:export writes.
     */
    public function getDirectory(): string
    {
        return Environment::getConfigPath() . '/scheduler';
    }

    /**
     * @return array<string, TaskDefinition>
     * @throws InvalidTaskDefinitionException
     */
    public function getDefinitions(): array
    {
        $projectDefinitions = $this->loader->loadFromDirectory($this->getDirectory());
        $definitions = [];
        foreach ($this->siteSetTaskDirectories->getDirectories() as $setName => $directory) {
            foreach ($this->loader->loadFromDirectory($directory) as $identifier => $definition) {
                // A project file replaces both, so two sets may share an identifier then.
                if (isset($definitions[$identifier]) && !isset($projectDefinitions[$identifier])) {
                    throw new InvalidTaskDefinitionException(
                        sprintf(
                            'Scheduler task "%s" is defined by more than one site set: "%s" and "%s" (set "%s"). Override it in config/scheduler/%s.yaml or rename one of them.',
                            $identifier,
                            $definitions[$identifier]->sourceFile,
                            $definition->sourceFile,
                            $setName,
                            $identifier
                        ),
                        1791360009
                    );
                }
                $definitions[$identifier] = $definition;
            }
        }
        return array_replace($definitions, $projectDefinitions);
    }

    /**
     * Only stats files, so it is cheap enough to run on every boot. A changed fingerprint
     * means "look again", never "something changed": the hashes decide that. The list of
     * directories is part of it, so adding a set to a site counts as a change.
     */
    public function getFingerprint(): string
    {
        $parts = [];
        $directories = array_values($this->siteSetTaskDirectories->getDirectories());
        $directories[] = $this->getDirectory();
        foreach ($directories as $directory) {
            $parts[] = $directory;
            $files = array_merge(glob($directory . '/*.yaml') ?: [], glob($directory . '/*.yml') ?: []);
            sort($files);
            foreach ($files as $file) {
                $parts[] = $file . ':' . (int)@filemtime($file) . ':' . (int)@filesize($file);
            }
        }
        return hash('sha256', implode("\n", $parts));
    }
}
