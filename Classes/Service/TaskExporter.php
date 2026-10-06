<?php

declare(strict_types=1);

namespace MaikSchneider\SchedulerAsCode\Service;

use MaikSchneider\SchedulerAsCode\Configuration\TaskDefinitionLoader;
use MaikSchneider\SchedulerAsCode\Configuration\TaskDefinitionProvider;
use MaikSchneider\SchedulerAsCode\Persistence\ManagedTaskRepository;
use MaikSchneider\SchedulerAsCode\Persistence\TaskStorageInterface;
use Symfony\Component\Yaml\Yaml;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Writes task records into task files and links them, so the next import finds them unchanged.
 */
class TaskExporter
{
    public function __construct(
        private readonly ManagedTaskRepository $managedTaskRepository,
        private readonly TaskStorageInterface $taskStorage,
        private readonly TaskDefinitionProvider $definitionProvider,
        private readonly TaskDefinitionLoader $loader,
    ) {
    }

    /**
     * @param array<string, mixed> $row
     * @param string|null $identifier null to keep the current one, or to derive one
     * @return string the written file
     */
    public function export(array $row, ?string $identifier = null): string
    {
        $configuration = $this->taskStorage->read($row);
        $identifier ??= (string)($row['tx_schedulerascode_identifier'] ?? '') ?: $this->deriveIdentifier($configuration);

        $directory = $this->definitionProvider->getDirectory();
        GeneralUtility::mkdir_deep($directory);
        $file = $directory . '/' . $identifier . '.yaml';
        GeneralUtility::writeFile(
            $file,
            Yaml::dump($this->normalize($configuration, (int)($row['task_group'] ?? 0)), 4, 2),
            true
        );

        // Hash what was written, not what was read, so the round trip through YAML counts.
        $definition = $this->loader->loadFile($file);
        $this->managedTaskRepository->markManaged((int)$row['uid'], $identifier, $definition->getHash());
        return $file;
    }

    /**
     * Fixed key order and no defaults, so files stay short and diffs stay readable.
     *
     * @param array<string, mixed> $configuration
     * @return array<string, mixed>
     */
    private function normalize(array $configuration, int $taskGroup): array
    {
        $normalized = ['type' => $configuration['type']];
        if (($configuration['description'] ?? '') !== '') {
            $normalized['description'] = $configuration['description'];
        }
        $group = $this->managedTaskRepository->findGroupName($taskGroup);
        if ($group !== '') {
            $normalized['group'] = $group;
        }
        if ($configuration['disabled'] ?? false) {
            $normalized['disabled'] = true;
        }
        if (isset($configuration['priority']) && (int)$configuration['priority'] !== 100) {
            $normalized['priority'] = (int)$configuration['priority'];
        }
        $normalized['execution'] = $configuration['execution'];
        if (($configuration['parameters'] ?? []) !== []) {
            $normalized['parameters'] = $configuration['parameters'];
        }
        return $normalized;
    }

    /**
     * @param array<string, mixed> $configuration
     */
    private function deriveIdentifier(array $configuration): string
    {
        $type = (string)$configuration['type'];
        $base = str_contains($type, '\\') ? substr((string)strrchr($type, '\\'), 1) : $type;
        $base = strtolower((string)preg_replace(['/(?<=[a-z0-9])([A-Z])/', '/[^a-zA-Z0-9]+/'], ['-$1', '-'], $base));
        $base = trim($base, '-') ?: 'task';

        $directory = $this->definitionProvider->getDirectory();
        $identifier = $base;
        for ($i = 2; file_exists($directory . '/' . $identifier . '.yaml') || file_exists($directory . '/' . $identifier . '.yml'); $i++) {
            $identifier = $base . '-' . $i;
        }
        return $identifier;
    }
}
