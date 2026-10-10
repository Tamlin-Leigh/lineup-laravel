<?php

namespace App\Livewire\Events;

use App\Models\Event;
use App\Models\User;
use Flux\Flux;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * The signed-in user's events, split into status tabs with search and paging.
 */
#[Title('Events')]
class Index extends Component
{
    use WithPagination;

    private const PER_PAGE = 6;

    #[Url]
    public string $tab = 'active';

    #[Url(as: 'q')]
    public string $search = '';

    public function updated(string $property): void
    {
        if (in_array($property, ['tab', 'search'], true)) {
            $this->resetPage();
        }
    }

    public function delete(int $eventId): void
    {
        $event = $this->events()->findOrFail($eventId);

        $this->authorize('delete', $event);

        $event->delete();

        Flux::toast(text: __('Event deleted.'));
    }

    /**
     * How many events sit in each tab, ignoring the search.
     *
     * @return array<string, int>
     */
    #[Computed]
    public function counts(): array
    {
        return collect(array_keys(Event::TABS))
            ->mapWithKeys(fn (string $tab) => [$tab => $this->events()->inTab($tab)->count()])
            ->all();
    }

    /**
     * @return LengthAwarePaginator<int, Event>
     */
    #[Computed]
    public function results(): LengthAwarePaginator
    {
        $tab = array_key_exists($this->tab, Event::TABS) ? $this->tab : 'active';
        $search = addcslashes(trim($this->search), '\\%_');

        return $this->events()
            ->inTab($tab)
            ->when($search !== '', fn (Builder $query) => $query->where(fn (Builder $query) => $query
                ->where('title', 'like', "%{$search}%")
                ->orWhere('location', 'like', "%{$search}%")))
            ->paginate(self::PER_PAGE);
    }

    public function render(): View
    {
        // Livewire would otherwise treat "Index" as the folder's own view (livewire/events.blade.php).
        return view('livewire.events.index');
    }

    /**
     * @return HasMany<Event, User>
     */
    private function events(): HasMany
    {
        /** @var User $user */
        $user = Auth::user();

        return $user->events();
    }
}
