<?php

namespace App\Actions;

use App\Models\User;
use App\Support\AvailabilityImportPreview;
use Carbon\CarbonImmutable;
use DateTimeImmutable;

/**
 * Bulk-sets an account's free days from a CSV that lists only the days they are free, one per row.
 * Between the first and last date in the file the calendar is made to match the file exactly:
 * listed days become free and any other free day in that range is cleared. That way a corrected
 * spreadsheet can simply be uploaded again. Days outside the file's range are left alone.
 */
class ImportAvailability
{
    /** Rows beyond this are rejected: two years of days, with room for a header and blank lines. */
    public const MAX_ROWS = 1000;

    /**
     * Accepted date formats, tried in order. Numeric dates are read day first (31/12/2026).
     */
    private const FORMATS = ['Y-m-d', 'Y/m/d', 'd/m/Y', 'd/m/y', 'd-m-Y', 'd.m.Y', 'j M Y', 'j F Y', 'D j M Y', 'l j F Y', 'D, j M Y', 'l, j F Y'];

    public function __construct(private UpdateAvailability $updateAvailability) {}

    /**
     * Read the dates out of a CSV. The first non-empty cell of each row is the date; anything after
     * it (notes, say) is ignored. A first row that isn't a date is taken to be a header.
     *
     * @return array{dates: list<string>, invalid: list<array{line: int, value: string}>, tooLong: bool}
     */
    public function parse(string $contents): array
    {
        $contents = preg_replace('/^\xEF\xBB\xBF/', '', $contents) ?? $contents;
        $lines = preg_split('/\r\n|\r|\n/', $contents) ?: [];
        $dates = [];
        $invalid = [];
        $rows = 0;

        foreach ($lines as $index => $line) {
            $cell = collect(str_getcsv($line, escape: ''))
                ->map(fn (?string $value) => trim((string) $value))
                ->first(fn (string $value) => $value !== '');

            if ($cell === null) {
                continue;
            }

            if (++$rows > self::MAX_ROWS) {
                return ['dates' => [], 'invalid' => [], 'tooLong' => true];
            }

            $date = $this->parseDate($cell);

            if ($date !== null) {
                $dates[$date] = true;
            } elseif ($rows > 1) {
                $invalid[] = ['line' => $index + 1, 'value' => mb_strimwidth($cell, 0, 40, '...')];
            }
        }

        $dates = array_keys($dates);
        sort($dates);

        return ['dates' => $dates, 'invalid' => $invalid, 'tooLong' => false];
    }

    /**
     * Work out what importing these dates would change, without saving anything.
     *
     * @param  list<string>  $dates  Y-m-d strings.
     */
    public function preview(User $user, array $dates): AvailabilityImportPreview
    {
        $outOfRange = [];
        $inRange = [];

        foreach ($dates as $date) {
            if ($this->updateAvailability->canChange(CarbonImmutable::parse($date))) {
                $inRange[] = $date;
            } else {
                $outOfRange[] = $date;
            }
        }

        if ($inRange === []) {
            return new AvailabilityImportPreview(null, null, [], [], [], $outOfRange, [], []);
        }

        $from = min($inRange);
        $to = max($inRange);
        $busyDays = $this->updateAvailability->busyDays($user, $from, $to);
        $currentlyFree = $user->availabilities()
            ->whereDate('date', '>=', $from)
            ->whereDate('date', '<=', $to)
            ->pluck('date')
            ->mapWithKeys(fn (CarbonImmutable $date) => [$date->format('Y-m-d') => true])
            ->all();

        $listed = array_fill_keys($inRange, true);
        $busy = array_values(array_filter($inRange, fn (string $date) => isset($busyDays[$date])));
        $unchanged = array_values(array_filter($inRange, fn (string $date) => isset($currentlyFree[$date])));
        $add = array_values(array_filter($inRange, fn (string $date) => ! isset($busyDays[$date]) && ! isset($currentlyFree[$date])));
        $remove = array_keys(array_diff_key($currentlyFree, $listed));
        sort($remove);

        $pending = $remove === [] ? [] : $this->updateAvailability->pendingRequestDays($user, $from, $to);

        return new AvailabilityImportPreview(
            from: CarbonImmutable::parse($from),
            to: CarbonImmutable::parse($to),
            add: $add,
            remove: $remove,
            unchanged: $unchanged,
            outOfRange: $outOfRange,
            busy: $busy,
            pendingClashes: array_intersect_key($pending, array_flip($remove)),
        );
    }

    /**
     * Save the import. The preview is worked out again here so it reflects the calendar as it is now.
     *
     * @param  list<string>  $dates  Y-m-d strings.
     * @return array{added: int, removed: int}
     */
    public function apply(User $user, array $dates): array
    {
        $preview = $this->preview($user, $dates);
        $toDays = fn (array $dates) => array_map(fn (string $date) => CarbonImmutable::parse($date), $dates);

        return [
            'removed' => $this->updateAvailability->remove($user, $toDays($preview->remove)),
            'added' => $this->updateAvailability->markFree($user, $toDays($preview->add)),
        ];
    }

    /**
     * A small example file: a header and the next few Fridays and Saturdays.
     */
    public function template(): string
    {
        $friday = today()->next(CarbonImmutable::FRIDAY);
        $rows = ['date'];

        for ($week = 0; $week < 3; $week++) {
            $rows[] = $friday->addWeeks($week)->toDateString();
            $rows[] = $friday->addWeeks($week)->addDay()->toDateString();
        }

        return implode("\n", $rows)."\n";
    }

    /**
     * The date as Y-m-d, or null if it isn't a real date in one of the accepted formats.
     */
    private function parseDate(string $value): ?string
    {
        foreach (self::FORMATS as $format) {
            $date = DateTimeImmutable::createFromFormat('!'.$format, $value);
            $errors = DateTimeImmutable::getLastErrors();

            // Warnings catch impossible dates such as 31/02/2026, which would otherwise roll over. A
            // two-digit year read as four digits (year 26) falls through to the d/m/y format.
            if ($date !== false && ($errors === false || ($errors['warning_count'] === 0 && $errors['error_count'] === 0)) && (int) $date->format('Y') >= 1000) {
                return $date->format('Y-m-d');
            }
        }

        return null;
    }
}
