<?php

namespace App\Livewire;

use App\Enums\UserRole;
use App\Models\User;
use App\Queries\AvailableAccounts;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Find venues, artists and organisers who are free on a weekend, a single day or a date range.
 * Every filter lives in the URL so a search can be bookmarked or shared.
 */
#[Title('Discover')]
class Discover extends Component
{
    use WithPagination;

    public const PER_PAGE = 12;

    /** Furthest ahead a search can look, matching how far ahead availability can be marked. */
    public const MAX_DAYS_AHEAD = 730;

    /** How many upcoming weekends the weekend picker offers. */
    public const WEEKENDS_OFFERED = 12;

    /** 'all', or a registrable role's value. */
    #[Url]
    public string $role = 'all';

    /** 'weekend', 'day' or 'range'. */
    #[Url]
    public string $when = 'weekend';

    /** The Friday of the chosen weekend, as Y-m-d. */
    #[Url]
    public string $weekend = '';

    #[Url]
    public string $day = '';

    #[Url]
    public string $from = '';

    #[Url]
    public string $to = '';

    #[Url(as: 'q')]
    public string $search = '';

    public function mount(): void
    {
        $this->weekend = $this->validDate($this->weekend) ?? $this->weekends()[0]->format('Y-m-d');
        $this->day = $this->validDate($this->day) ?? today()->format('Y-m-d');
        $this->from = $this->validDate($this->from) ?? today()->format('Y-m-d');
        $this->to = $this->validDate($this->to) ?? today()->addDays(13)->format('Y-m-d');
    }

    public function updated(string $property): void
    {
        $this->resetPage();
    }

    /**
     * The first and last day being searched, after tidying up whatever the filters say.
     *
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    public function range(): array
    {
        $today = CarbonImmutable::today();

        [$from, $to] = match ($this->when) {
            'day' => [$this->date($this->day) ?? $today, $this->date($this->day) ?? $today],
            'range' => [$this->date($this->from) ?? $today, $this->date($this->to) ?? $today->addDays(13)],
            default => $this->weekendRange($this->date($this->weekend) ?? $this->weekends()[0]),
        };

        $from = $from->max($today);
        $to = $to->min($today->addDays(self::MAX_DAYS_AHEAD))->max($from);

        return [$from, $to];
    }

    /**
     * The Fridays of the next few weekends (including the current one while it is still on).
     *
     * @return list<CarbonImmutable>
     */
    public function weekends(): array
    {
        $today = CarbonImmutable::today();
        // On Saturday or Sunday the current weekend's Friday has passed, but its remaining days still count.
        $friday = $today->isWeekend() ? $today->previous(CarbonImmutable::FRIDAY) : $today->next(CarbonImmutable::FRIDAY);
        $friday = $today->isFriday() ? $today : $friday;

        return array_map(fn (int $week) => $friday->addWeeks($week), range(0, self::WEEKENDS_OFFERED - 1));
    }

    /**
     * @return array<string, string> role value => label, starting with "All".
     */
    public function roleOptions(): array
    {
        return ['all' => __('All')] + [
            UserRole::Venue->value => __('Venues'),
            UserRole::Artist->value => __('Artists'),
            UserRole::Organiser->value => __('Organisers'),
        ];
    }

    /**
     * @return LengthAwarePaginator<int, User>
     */
    #[Computed]
    public function results(): LengthAwarePaginator
    {
        [$from, $to] = $this->range();
        $role = UserRole::tryFrom($this->role);

        /** @var User $viewer */
        $viewer = Auth::user();

        return app(AvailableAccounts::class)
            ->query($viewer, $from, $to, $role ? [$role] : [], $this->search)
            ->paginate(self::PER_PAGE);
    }

    /**
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    private function weekendRange(CarbonImmutable $friday): array
    {
        return [$friday, $friday->addDays(2)];
    }

    private function date(string $value): ?CarbonImmutable
    {
        return $this->validDate($value) !== null ? CarbonImmutable::createFromFormat('!Y-m-d', $value) ?: null : null;
    }

    private function validDate(string $value): ?string
    {
        if (! CarbonImmutable::canBeCreatedFromFormat($value, '!Y-m-d')) {
            return null;
        }

        $date = CarbonImmutable::createFromFormat('!Y-m-d', $value);

        return $date instanceof CarbonImmutable && $date->format('Y-m-d') === $value ? $value : null;
    }
}
