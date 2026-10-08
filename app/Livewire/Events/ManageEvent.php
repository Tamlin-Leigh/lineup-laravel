<?php

namespace App\Livewire\Events;

use App\Livewire\Forms\EventForm;
use App\Models\Event;
use App\Models\User;
use App\Models\VenueProfile;
use Flux\Flux;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Create a new event (optionally on a date picked in the calendar) or edit an existing one.
 */
class ManageEvent extends Component
{
    #[Locked]
    public ?Event $event = null;

    public EventForm $form;

    /** Pre-selected date from the calendar's "Add Event", as Y-m-d. */
    #[Url]
    public string $date = '';

    public function mount(?Event $event = null): void
    {
        if ($event?->exists) {
            $this->authorize('update', $event);

            $this->event = $event;
            $this->form->fillFromEvent($event);

            return;
        }

        $this->authorize('create', Event::class);

        $this->form->date = $this->validDate($this->date) ?? now()->addDay()->format('Y-m-d');

        // Venues usually host their own events, so start from their venue.
        if ($venue = $this->user()->venueProfile) {
            $this->form->venue_profile_id = (string) $venue->id;
            $this->form->location = $venue->location;
        }
    }

    /**
     * Fill the location from a BandLink venue when one is picked and nothing has been typed yet.
     */
    public function updatedFormVenueProfileId(string $id): void
    {
        $venue = $id !== '' ? VenueProfile::find($id) : null;

        if ($venue && trim($this->form->location) === '') {
            $this->form->location = $venue->location;
        }
    }

    public function save(): void
    {
        $isNew = $this->event === null;

        $this->authorize($isNew ? 'create' : 'update', $this->event ?? Event::class);

        $attributes = $this->form->attributes($isNew);

        if ($this->event === null) {
            $this->user()->events()->create($attributes);
        } else {
            $this->event->update($attributes);
        }

        Flux::toast(variant: 'success', text: $isNew ? __('Event created.') : __('Event saved.'));

        $this->redirectRoute('events.index', navigate: true);
    }

    /**
     * BandLink venues an event can be linked to.
     *
     * @return Collection<int, VenueProfile>
     */
    #[Computed]
    public function venues(): Collection
    {
        return VenueProfile::query()
            ->with('user:id,name')
            ->get()
            ->sortBy(fn (VenueProfile $venue) => $venue->user->name)
            ->values();
    }

    public function render(): mixed
    {
        return view('livewire.events.manage-event')
            ->title($this->event ? __('Edit event') : __('New event'));
    }

    private function user(): User
    {
        /** @var User */
        return Auth::user();
    }

    private function validDate(string $value): ?string
    {
        return Carbon::canBeCreatedFromFormat($value, 'Y-m-d') ? $value : null;
    }
}
