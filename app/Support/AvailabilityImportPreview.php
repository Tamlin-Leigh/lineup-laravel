<?php

namespace App\Support;

use Carbon\CarbonImmutable;

/**
 * What importing a list of free days would change, shown to the user before anything is saved.
 * Every date list is Y-m-d strings, soonest first.
 */
final readonly class AvailabilityImportPreview
{
    /**
     * @param  list<string>  $add  Days that will become free.
     * @param  list<string>  $remove  Free days in the file's date range that the file leaves out, so will be cleared.
     * @param  list<string>  $unchanged  Days in the file that are already free.
     * @param  list<string>  $outOfRange  Days in the file that are past or more than two years ahead.
     * @param  list<string>  $busy  Days in the file on which the user is hosting or booked for an event.
     * @param  array<string, list<string>>  $pendingClashes  Days being cleared that have booking requests waiting for a reply, as Y-m-d => event titles.
     */
    public function __construct(
        public ?CarbonImmutable $from,
        public ?CarbonImmutable $to,
        public array $add,
        public array $remove,
        public array $unchanged,
        public array $outOfRange,
        public array $busy,
        public array $pendingClashes,
    ) {}

    public function hasChanges(): bool
    {
        return $this->add !== [] || $this->remove !== [];
    }
}
