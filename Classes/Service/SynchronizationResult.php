<?php

declare(strict_types=1);

namespace MaikSchneider\SchedulerAsCode\Service;

final class SynchronizationResult
{
    /** @var list<string> */
    public array $created = [];
    /** @var list<string> */
    public array $updated = [];
    /** @var list<string> */
    public array $disabled = [];
    /** @var array<string, string> identifier => reason */
    public array $failed = [];

    public function hasChanges(): bool
    {
        return $this->created !== [] || $this->updated !== [] || $this->disabled !== [];
    }
}
