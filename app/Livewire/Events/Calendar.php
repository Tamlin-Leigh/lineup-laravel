<?php

namespace App\Livewire\Events;

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
 * Month view of the signed-in user's events (cancelled ones are left off).
 */
#[Title('Calendar')]
class Calendar extends Component
{
    /** The month on show, as Y-m; empty means the current month. */
    #[Url]
    public string $month = '';

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
        /** @var User $user */
        $user = Auth::user();

        return Event::query()
            ->whereBelongsTo($user, 'owner')
            ->where('status', '!=', EventStatus::Cancelled)
            ->orderBy('starts_at');
    }
}
