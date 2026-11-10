<?php

namespace App\Livewire\Events;

use App\Enums\EventStatus;
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

    /**
     * Publish a confirmed draft straight from the list. It's checked again here, as an artist could
     * have dropped out since the page loaded.
     */
    public function publish(int $eventId): void
    {
        $event = $this->events()->findOrFail($eventId);

        $this->authorize('update', $event);

        if (! $event->canBePublished()) {
            Flux::toast(variant: 'danger', text: __('This event can no longer be published. Open it to see what still needs confirming.'));

            return;
        }

        $event->update(['status' => EventStatus::Published]);

        Flux::toast(variant: 'success', text: __(':title is published.', ['title' => $event->title]));
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
