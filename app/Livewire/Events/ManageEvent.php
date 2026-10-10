<?php

namespace App\Livewire\Events;

use App\Actions\CancelBookings;
use App\Actions\SendBookingRequests;
use App\Actions\UpdateAvailability;
use App\Enums\EventStatus;
use App\Enums\UserRole;
use App\Livewire\Forms\EventForm;
use App\Models\BookingRequest;
use App\Models\Event;
use App\Models\User;
use App\Queries\AvailableAccounts;
use Flux\Flux;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Create a new event (optionally on a date picked in the calendar) or edit an existing one, and
 * send booking requests to the bands (and, for bands and organisers, the venue) free on its date.
 * This is the one place booking happens, for every role.
 *
 * A new event is a short wizard in the order the choices depend on each other: when (which decides
 * who is free), where (a free venue, or somewhere else), the line-up, then the details. Editing is
 * a single page.
 *
 * @property-read Collection<int, User> $freeBands
 * @property-read Collection<int, User> $freeVenues
 */
class ManageEvent extends Component
{
    /** How many booking requests one account may send per hour. */
    public const BOOKING_REQUESTS_PER_HOUR = 50;

    #[Locked]
    public ?Event $event = null;

    /** The wizard step a new event is on (see steps()). */
    #[Locked]
    public int $step = 0;

    public EventForm $form;

    /** Pre-selected date from the calendar's "Add Event", as Y-m-d. */
    #[Url]
    public string $date = '';

    /**
     * Bands ticked to receive a booking request when the event is saved.
     *
     * @var list<string>
     */
    public array $bandIds = [];

    /** The venue picked to host the event (an event has one venue), or ''. */
    public string $venueId = '';

    public string $bookingMessage = '';

    public string $bandSearch = '';

    public string $venueSearch = '';

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

        // A venue's events are held at its own venue.
        if ($venue = $this->user()->venueProfile) {
            $this->form->location = $venue->location;
        }
    }

    /**
     * The new event wizard's steps for this account: venues skip "where", as their events are at
     * their own venue.
     *
     * @return array<string, string> Step key => label.
     */
    public function steps(): array
    {
        $steps = ['when' => 'When'];

        if ($this->canBookVenue()) {
            $steps['where'] = 'Where';
        }

        if ($this->canSendBookingRequests()) {
            $steps['line-up'] = 'Line-up';
        }

        $steps['details'] = 'Details';

        return array_map(fn (string $label) => (string) __($label), $steps);
    }

    public function currentStep(): string
    {
        return array_keys($this->steps())[$this->step] ?? 'details';
    }

    public function isLastStep(): bool
    {
        return $this->step >= count($this->steps()) - 1;
    }

    /**
     * Check the current step, then move on (or create the event on the last step).
     */
    public function next(): void
    {
        match ($this->currentStep()) {
            'when' => $this->form->validateFields(['date', 'time']),
            // Somewhere else needs a location; a venue's location is filled in from the venue.
            'where' => $this->venueId === '' ? $this->form->validateFields(['location']) : null,
            default => null,
        };

        if ($this->isLastStep()) {
            $this->save();

            return;
        }

        $this->step++;
    }

    public function back(): void
    {
        $this->resetErrorBag();

        if ($this->step === 0) {
            $this->redirectRoute('events.index', navigate: true);

            return;
        }

        $this->step--;
    }

    /**
     * Everyone picked so far, for the summary before a new event is created.
     *
     * @return Collection<int, User>
     */
    public function pickedAccounts(): Collection
    {
        $ids = array_map('intval', $this->recipientIds());

        return $this->freeVenues->concat($this->freeBands)
            ->filter(fn (User $account) => in_array($account->id, $ids, true))
            ->values();
    }

    /**
     * A new date means different accounts are free: keep only the picks still free on it.
     */
    public function updatedFormDate(): void
    {
        unset($this->freeBands, $this->freeVenues);

        $free = $this->freeBands->pluck('id')->map(fn (int $id) => (string) $id)->all();
        $this->bandIds = array_values(array_intersect($this->bandIds, $free));

        if (! $this->freeVenues->contains('id', (int) $this->venueId)) {
            $this->venueId = '';
        }
    }

    public function save(): void
    {
        $isNew = $this->event === null;

        $this->authorize($isNew ? 'create' : 'update', $this->event ?? Event::class);

        $this->fillLocationFromVenue();

        $attributes = $this->form->attributes($isNew);
        $recipientIds = $this->recipientIds();
        $this->validateBooking($attributes, $recipientIds);

        $wasCancelled = $this->event?->status === EventStatus::Cancelled;

        if ($this->event === null) {
            $this->event = $this->user()->events()->create($attributes + [
                'venue_profile_id' => $this->user()->venueProfile?->id,
            ]);
        } else {
            $this->event->update($attributes);
        }

        if ($this->event->status === EventStatus::Cancelled) {
            if (! $wasCancelled) {
                app(CancelBookings::class)->handle($this->event);
            }
        } else {
            // The host has set the day aside, so they're no longer free for other events on it.
            app(UpdateAvailability::class)->markBusy($this->user(), $this->event->starts_at);
        }

        $sent = $recipientIds !== []
            ? app(SendBookingRequests::class)->handle($this->event, $recipientIds, $this->bookingMessage)->count()
            : 0;

        if ($sent > 0) {
            RateLimiter::increment($this->rateLimitKey(), 3600, $sent);
        }

        $saved = $isNew ? __('Event created.') : __('Event saved.');

        Flux::toast(variant: 'success', text: $sent > 0
            ? $saved.' '.trans_choice(':count booking request sent.|:count booking requests sent.', $sent)
            : $saved);

        $this->redirectRoute('events.index', navigate: true);
    }

    /**
     * Whether this account can send booking requests for the event (new, or its own upcoming one).
     */
    public function canSendBookingRequests(): bool
    {
        return $this->user()->bookableRoles() !== []
            && ($this->event === null || $this->user()->can('sendBookingRequests', $this->event));
    }

    /**
     * Bands and organisers can ask a venue to host, while the event doesn't have one yet.
     */
    public function canBookVenue(): bool
    {
        return $this->canSendBookingRequests()
            && $this->user()->canBook(UserRole::Venue)
            && ($this->event === null || $this->event->needsVenue());
    }

    /**
     * Bands free on the chosen date who haven't been asked for this event yet.
     *
     * @return Collection<int, User>
     */
    #[Computed]
    public function freeBands(): Collection
    {
        return $this->canSendBookingRequests()
            ? $this->freeAccounts(UserRole::Band, $this->bandSearch)
            : new Collection;
    }

    /**
     * Venues free on the chosen date who haven't been asked for this event yet.
     *
     * @return Collection<int, User>
     */
    #[Computed]
    public function freeVenues(): Collection
    {
        return $this->canBookVenue()
            ? $this->freeAccounts(UserRole::Venue, $this->venueSearch)
            : new Collection;
    }

    /**
     * The bands playing. A band creating an event is on its line-up from the start.
     *
     * @return Collection<int, User>
     */
    #[Computed]
    public function lineUp(): Collection
    {
        if ($this->event !== null) {
            return $this->event->lineUp();
        }

        return $this->user()->role === UserRole::Band
            ? new Collection([$this->user()->loadMissing('bandProfile')])
            : new Collection;
    }

    /**
     * Bands and venues already asked to take part in this event.
     *
     * @return Collection<int, BookingRequest>
     */
    #[Computed]
    public function bookingRequests(): Collection
    {
        if ($this->event === null) {
            return new Collection;
        }

        return $this->event->bookingRequests()
            ->with(['event.owner', 'recipient.bandProfile', 'recipient.venueProfile'])
            ->latest()
            ->get();
    }

    public function render(): mixed
    {
        return view('livewire.events.manage-event')
            ->title($this->event ? __('Edit event') : __('New event'));
    }

    /**
     * @return Collection<int, User>
     */
    private function freeAccounts(UserRole $role, string $search): Collection
    {
        $date = $this->validDate($this->form->date);

        if ($date === null || $date < today()->format('Y-m-d')) {
            return new Collection;
        }

        $day = Carbon::createFromFormat('Y-m-d', $date)->toImmutable()->startOfDay();

        return app(AvailableAccounts::class)
            ->query($this->user(), $day, $day, [$role], $search)
            ->when($this->event, fn (Builder $query, Event $event) => $query
                ->whereDoesntHave('receivedBookingRequests', fn (Builder $requests) => $requests->where('event_id', $event->id)))
            ->limit(50)
            ->get();
    }

    /**
     * A new event at a venue takes the venue's location (the wizard doesn't ask for one); on an
     * existing event it only fills an empty location. It's replaced again once the venue accepts.
     */
    private function fillLocationFromVenue(): void
    {
        $venue = $this->venueId !== '' ? $this->freeVenues->firstWhere('id', (int) $this->venueId) : null;

        if ($venue?->venueProfile && ($this->event === null || trim($this->form->location) === '')) {
            $this->form->location = $venue->name.', '.$venue->venueProfile->location;
        }
    }

    /**
     * Everyone picked to receive a booking request.
     *
     * @return list<string>
     */
    private function recipientIds(): array
    {
        return array_values(array_filter([...$this->bandIds, $this->venueId]));
    }

    /**
     * Picks need an upcoming, uncancelled event, a sensible message and some rate limit headroom.
     *
     * @param  array<string, mixed>  $attributes  The validated event attributes about to be saved.
     * @param  list<string>  $recipientIds
     */
    private function validateBooking(array $attributes, array $recipientIds): void
    {
        if ($recipientIds === []) {
            return;
        }

        $this->validate([
            'bandIds' => ['array', 'max:50'],
            'bandIds.*' => ['integer'],
            'venueId' => ['nullable', 'integer'],
            'bookingMessage' => ['nullable', 'string', 'max:500'],
        ], attributes: ['bookingMessage' => __('message')]);

        $error = match (true) {
            $this->user()->bookableRoles() === [] => __('Your account can\'t send booking requests.'),
            $attributes['status'] === EventStatus::Cancelled->value => __('Booking requests can\'t be sent for a cancelled event.'),
            $attributes['starts_at']->isPast() => __('Booking requests can only be sent for an event that is still to come.'),
            RateLimiter::remaining($this->rateLimitKey(), self::BOOKING_REQUESTS_PER_HOUR) < count($recipientIds) => __('You have sent a lot of booking requests in the last hour. Please try again later.'),
            default => null,
        };

        if ($error !== null) {
            throw ValidationException::withMessages(['booking' => $error]);
        }
    }

    private function rateLimitKey(): string
    {
        return 'booking-requests:'.$this->user()->id;
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
