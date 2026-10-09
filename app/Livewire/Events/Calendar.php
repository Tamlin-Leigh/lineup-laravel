<?php

namespace App\Livewire\Events;

use App\Actions\UpdateAvailability;
use App\Enums\EventStatus;
use App\Models\Event;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Collection as SupportCollection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Month view of the signed-in user's events (cancelled ones are left off) and the days they have
 * marked as free for a gig. In "mark availability" mode, clicking a day toggles it.
 */
#[Title('Calendar')]
class Calendar extends Component
{
    /** The month on show, as Y-m; empty means the current month. */
    #[Url]
    public string $month = '';

    public bool $markingAvailability = false;

    public function previousMonth(): void
    {
        $this->month = $this->visibleMonth()->subMonth()->format('Y-m');
    }

    public function nextMonth(): void
    {
        $this->month = $this->visibleMonth()->addMonth()->format('Y-m');
    }

    public function today(): void
    {
        $this->month = '';
    }

    public function toggleDay(string $date, UpdateAvailability $updateAvailability): void
    {
        // Reject anything that isn't a real calendar date (e.g. "2026-02-31" would roll over to March).
        if (! CarbonImmutable::canBeCreatedFromFormat($date, '!Y-m-d')) {
            return;
        }

        $day = CarbonImmutable::createFromFormat('!Y-m-d', $date);

        if (! $day instanceof CarbonImmutable || $day->format('Y-m-d') !== $date) {
            return;
        }

        $updateAvailability->toggle($this->user(), $day);
    }

    /**
     * Mark every Friday, Saturday and Sunday left in the visible month as free.
     */
    public function markWeekends(UpdateAvailability $updateAvailability): void
    {
        $weekendDays = collect($this->weeks())
            ->flatten()
            ->filter(fn (?CarbonImmutable $day) => $day !== null && in_array($day->dayOfWeek, [5, 6, 0], true));

        $updateAvailability->markFree($this->user(), $weekendDays);
    }

    public function clearMonth(UpdateAvailability $updateAvailability): void
    {
        $month = $this->visibleMonth();

        $updateAvailability->clear($this->user(), $month, $month->endOfMonth());
    }

    public function visibleMonth(): CarbonImmutable
    {
        if (preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $this->month) === 1) {
            return CarbonImmutable::createFromFormat('!Y-m', $this->month) ?: CarbonImmutable::now()->startOfMonth();
        }

        return CarbonImmutable::now()->startOfMonth();
    }

    /**
     * The month as whole weeks, Sunday first; days outside the month are null.
     *
     * @return list<list<CarbonImmutable|null>>
     */
    #[Computed]
    public function weeks(): array
    {
        $month = $this->visibleMonth();
        $days = array_fill(0, $month->dayOfWeek, null);

        for ($day = $month; $day->month === $month->month; $day = $day->addDay()) {
            $days[] = $day;
        }

        while (count($days) % 7 !== 0) {
            $days[] = null;
        }

        return array_chunk($days, 7);
    }

    /**
     * This month's events keyed by Y-m-d.
     *
     * @return SupportCollection<string, Collection<int, Event>>
     */
    #[Computed]
    public function eventsByDay(): SupportCollection
    {
        $month = $this->visibleMonth();

        return $this->calendarEvents()
            ->whereBetween('starts_at', [$month, $month->endOfMonth()])
            ->get()
            ->groupBy(fn (Event $event) => $event->starts_at->format('Y-m-d'));
    }

    /**
     * Free days in the visible month, as a Y-m-d => true lookup.
     *
     * @return array<string, bool>
     */
    #[Computed]
    public function freeDays(): array
    {
        $month = $this->visibleMonth();

        return $this->user()->availabilities()
            ->whereDate('date', '>=', $month->toDateString())
            ->whereDate('date', '<=', $month->endOfMonth()->toDateString())
            ->pluck('date')
            ->mapWithKeys(fn (CarbonImmutable $date) => [$date->format('Y-m-d') => true])
            ->all();
    }

    /**
     * The next few free days from today, for the side panel.
     *
     * @return SupportCollection<int, CarbonImmutable>
     */
    #[Computed]
    public function upcomingFreeDays(): SupportCollection
    {
        return $this->user()->availabilities()->upcoming()->limit(6)->pluck('date');
    }

    public function canChange(CarbonImmutable $day): bool
    {
        return app(UpdateAvailability::class)->canChange($day);
    }

    /**
     * @return Collection<int, Event>
     */
    #[Computed]
    public function todaysEvents(): Collection
    {
        return $this->calendarEvents()
            ->whereBetween('starts_at', [now()->startOfDay(), now()->endOfDay()])
            ->get();
    }

    /**
     * @return Builder<Event>
     */
    private function calendarEvents(): Builder
    {
        return Event::query()
            ->whereBelongsTo($this->user(), 'owner')
            ->where('status', '!=', EventStatus::Cancelled)
            ->orderBy('starts_at');
    }

    private function user(): User
    {
        /** @var User */
        return Auth::user();
    }
}
