<?php

declare(strict_types=1);

namespace MaikSchneider\SchedulerAsCode\Configuration;

use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;

final class TaskDefinitionLoader
{
    private const IDENTIFIER_PATTERN = '/^[a-z0-9][a-z0-9_-]*$/';

    /**
     * @return array<string, TaskDefinition> keyed by identifier
     * @throws InvalidTaskDefinitionException
     */
    public function loadFromDirectory(string $directory): array
    {
        if (!is_dir($directory)) {
            return [];
        }

        $directory = rtrim($directory, '/');
        $files = array_merge(glob($directory . '/*.yaml') ?: [], glob($directory . '/*.yml') ?: []);
        sort($files);

        $definitions = [];
        foreach ($files as $file) {
            $definition = $this->loadFile($file);
            if (isset($definitions[$definition->identifier])) {
                throw new InvalidTaskDefinitionException(
                    sprintf(
                        'Scheduler task "%s" is defined twice: in "%s" and "%s".',
                        $definition->identifier,
                        $definitions[$definition->identifier]->sourceFile,
                        $file
                    ),
                    1791360001
                );
            }
            $definitions[$definition->identifier] = $definition;
        }
        return $definitions;
    }

    /**
     * @throws InvalidTaskDefinitionException
     */
    public function loadFile(string $file): TaskDefinition
    {
        $identifier = pathinfo($file, PATHINFO_FILENAME);
        if (preg_match(self::IDENTIFIER_PATTERN, $identifier) !== 1) {
            throw new InvalidTaskDefinitionException(
                sprintf(
                    'File name "%s" is not a valid task identifier. Use lowercase letters, digits, "-" and "_".',
                    basename($file)
                ),
                1791360002
            );
        }

        try {
            $configuration = Yaml::parseFile($file);
        } catch (ParseException $e) {
            throw new InvalidTaskDefinitionException(
                sprintf('Scheduler task file "%s" is not valid YAML: %s', $file, $e->getMessage()),
                1791360003,
                $e
            );
        }

        if (!is_array($configuration) || array_is_list($configuration)) {
            throw new InvalidTaskDefinitionException(
                sprintf('Scheduler task file "%s" must contain a mapping.', $file),
                1791360004
            );
        }
        /** @var array<string, mixed> $configuration */
        if (!is_string($configuration['type'] ?? null) || $configuration['type'] === '') {
            throw new InvalidTaskDefinitionException(
                sprintf('Scheduler task file "%s" needs a "type": a console command name or a task class.', $file),
                1791360005
            );
        }
        $execution = $configuration['execution'] ?? [];
        if (!is_array($execution) || (!isset($execution['frequency']) && !isset($execution['start']))) {
            throw new InvalidTaskDefinitionException(
                sprintf('Scheduler task file "%s" needs "execution.frequency", or "execution.start" for a single run.', $file),
                1791360006
            );
        }
        if (isset($configuration['parameters']) && !is_array($configuration['parameters'])) {
            throw new InvalidTaskDefinitionException(
                sprintf('"parameters" in scheduler task file "%s" must be a mapping.', $file),
                1791360007
            );
        }

        return new TaskDefinition($identifier, $configuration, $file);
    }
}
