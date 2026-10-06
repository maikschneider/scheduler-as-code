<?php

declare(strict_types=1);

namespace MaikSchneider\SchedulerAsCode\Configuration;

/**
 * A scheduler task as declared in a YAML file. The identifier is the file name without its
 * extension and is what links the file to its database record.
 */
final readonly class TaskDefinition
{
    /**
     * @param array<string, mixed> $configuration
     */
    public function __construct(
        public string $identifier,
        public array $configuration,
        public string $sourceFile,
    ) {
    }

    /**
     * Stable across key order and formatting, so reordering or reformatting a file does not
     * count as a change.
     */
    public function getHash(): string
    {
        return hash('sha256', (string)json_encode(self::sortRecursive($this->configuration)));
    }

    /**
     * @param array<mixed> $value
     * @return array<mixed>
     */
    private static function sortRecursive(array $value): array
    {
        if (!array_is_list($value)) {
            ksort($value);
        }
        foreach ($value as $key => $item) {
            if (is_array($item)) {
                $value[$key] = self::sortRecursive($item);
            }
        }
        return $value;
    }
}
