<?php

declare(strict_types=1);

namespace MaikSchneider\SchedulerAsCode\Configuration;

use TYPO3\CMS\Core\Core\Environment;

/**
 * Knows where task files live and whether they changed since the last look.
 */
class TaskDefinitionProvider
{
    public function __construct(
        private readonly TaskDefinitionLoader $loader,
    ) {
    }

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
        return $this->loader->loadFromDirectory($this->getDirectory());
    }

    /**
     * Only stats the files, so it is cheap enough to run on every boot. A changed fingerprint
     * means "look again", never "something changed": the hashes decide that.
     */
    public function getFingerprint(): string
    {
        $directory = $this->getDirectory();
        $files = array_merge(glob($directory . '/*.yaml') ?: [], glob($directory . '/*.yml') ?: []);
        sort($files);
        $parts = [];
        foreach ($files as $file) {
            $parts[] = $file . ':' . (int)@filemtime($file) . ':' . (int)@filesize($file);
        }
        return hash('sha256', implode("\n", $parts));
    }
}
