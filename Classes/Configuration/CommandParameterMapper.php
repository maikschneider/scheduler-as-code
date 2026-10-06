<?php

declare(strict_types=1);

namespace MaikSchneider\SchedulerAsCode\Configuration;

/**
 * Console command tasks store options as two maps, "options" (enabled yes/no) and
 * "optionValues". Task files use one map instead, where a flag is just `true`:
 *
 *   parameters:
 *     arguments:
 *       table: sys_log
 *     options:
 *       min-age: 30
 *       dry-run: true
 */
final class CommandParameterMapper
{
    /**
     * @param array<string, mixed> $parameters
     * @return array{commandIdentifier: string, arguments: array<string, mixed>, options: array<string, bool>, optionValues: array<string, mixed>}
     */
    public function toCore(string $commandIdentifier, array $parameters): array
    {
        $options = [];
        $optionValues = [];
        foreach ((array)($parameters['options'] ?? []) as $name => $value) {
            if ($value === false || $value === null) {
                continue;
            }
            $options[(string)$name] = true;
            if ($value !== true) {
                $optionValues[(string)$name] = $value;
            }
        }
        /** @var array<string, mixed> $arguments */
        $arguments = (array)($parameters['arguments'] ?? []);
        return [
            'commandIdentifier' => $commandIdentifier,
            'arguments' => $arguments,
            'options' => $options,
            'optionValues' => $optionValues,
        ];
    }

    /**
     * @param array<array-key, mixed> $arguments
     * @param array<array-key, mixed> $options
     * @param array<array-key, mixed> $optionValues
     * @return array<string, mixed>
     */
    public function fromCore(array $arguments, array $options, array $optionValues): array
    {
        $parameters = [];
        $arguments = array_filter($arguments, static fn (mixed $value): bool => $value !== null && $value !== '' && $value !== []);
        if ($arguments !== []) {
            $parameters['arguments'] = $arguments;
        }
        $enabled = [];
        foreach ($options as $name => $isEnabled) {
            if ((bool)$isEnabled) {
                $value = $optionValues[$name] ?? null;
                $enabled[(string)$name] = $value === null || $value === '' ? true : $value;
            }
        }
        if ($enabled !== []) {
            $parameters['options'] = $enabled;
        }
        return $parameters;
    }
}
