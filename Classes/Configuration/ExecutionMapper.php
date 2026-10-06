<?php

declare(strict_types=1);

namespace MaikSchneider\SchedulerAsCode\Configuration;

use TYPO3\CMS\Scheduler\Execution;

/**
 * Translates between the "execution" section of a task file and the scheduler's Execution.
 *
 *   execution:
 *     frequency: '0 3 * * *'   # cron expression, or an interval in seconds
 *     start: '2026-01-01 03:00' # optional for recurring tasks, required for a single run
 *     end: '2026-12-31'         # optional
 *     multiple: true            # allow parallel executions, default false
 */
final class ExecutionMapper
{
    /**
     * @param array<string, mixed> $configuration
     */
    public function toExecution(array $configuration, int $now): Execution
    {
        $frequency = $configuration['frequency'] ?? null;
        $execution = new Execution();
        // Execution::isStarted() compares start < time(), so a start of "now" counts as not yet
        // started and the first run would be scheduled for this very second, regardless of the
        // frequency. One second earlier lets the frequency decide.
        $execution->setStart(isset($configuration['start']) ? $this->toTimestamp($configuration['start']) : $now - 1);
        $execution->setEnd(isset($configuration['end']) ? $this->toTimestamp($configuration['end']) : 0);
        $execution->setMultiple((bool)($configuration['multiple'] ?? false));

        if ($frequency === null || $frequency === '') {
            $execution->setInterval(0);
            $execution->setCronCmd('');
            $execution->setIsNewSingleExecution(true);
        } elseif (is_int($frequency) || ctype_digit((string)$frequency)) {
            $execution->setInterval((int)$frequency);
            $execution->setCronCmd('');
        } else {
            $execution->setInterval(0);
            $execution->setCronCmd((string)$frequency);
        }
        return $execution;
    }

    /**
     * Drops what the file does not need: the start of a cron task is only the creation time of
     * the record, and defaults are left out.
     *
     * @return array<string, mixed>
     */
    public function fromExecution(Execution $execution): array
    {
        $cronCmd = (string)$execution->getCronCmd();
        $interval = (int)$execution->getInterval();
        $configuration = [];
        if ($cronCmd !== '') {
            $configuration['frequency'] = $cronCmd;
        } elseif ($interval > 0) {
            $configuration['frequency'] = $interval;
            $configuration['start'] = $this->toDate((int)$execution->getStart());
        } else {
            $configuration['start'] = $this->toDate((int)$execution->getStart());
        }
        if ((int)$execution->getEnd() > 0) {
            $configuration['end'] = $this->toDate((int)$execution->getEnd());
        }
        if ($this->isMultiple($execution)) {
            $configuration['multiple'] = true;
        }
        return $configuration;
    }

    /**
     * TYPO3 13 only offers getMultiple(), TYPO3 14 only toArray(); the property is in both.
     */
    private function isMultiple(Execution $execution): bool
    {
        return (bool)(new \ReflectionProperty(Execution::class, 'multiple'))->getValue($execution);
    }

    private function toTimestamp(mixed $value): int
    {
        if (is_int($value)) {
            return $value;
        }
        try {
            return (new \DateTimeImmutable((string)$value))->getTimestamp();
        } catch (\Exception $e) {
            throw new InvalidTaskDefinitionException(
                sprintf('"%s" is not a valid date.', (string)$value),
                1791360008,
                $e
            );
        }
    }

    private function toDate(int $timestamp): string
    {
        return (new \DateTimeImmutable('@' . $timestamp))
            ->setTimezone(new \DateTimeZone(date_default_timezone_get()))
            ->format(\DateTimeInterface::ATOM);
    }
}
