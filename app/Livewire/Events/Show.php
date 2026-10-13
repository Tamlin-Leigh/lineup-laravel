<?php

namespace App\Livewire\Events;

use App\Actions\AnswerBookingRequest;
use App\Models\BookingRequest;
use App\Models\Event;
use App\Models\User;
use Flux\Flux;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * An event's details, read-only. Bands and venues open it from a booking request to see what
 * they're being asked to join, and can accept or decline from here too.
 *
 * @property-read Collection<int, User> $lineUp
 * @property-read BookingRequest|null $booking
 */
class Show extends Component
{
    public Event $event;

    public function mount(Event $event): void
    {
        $this->authorize('view', $event);
    }

    /**
     * @return Collection<int, User>
     */
    #[Computed]
    public function lineUp(): Collection
    {
        return $this->event->lineUp();
    }

    /**
     * The latest request this account received for the event, if any.
     */
    #[Computed]
    public function booking(): ?BookingRequest
    {
        return $this->event->bookingRequests()->where('recipient_id', $this->user()->id)->latest()->first();
    }

    public function accept(AnswerBookingRequest $answer): void
    {
        $booking = $this->bookingForReply();
        $answer->accept($booking);

        Flux::toast(variant: 'success', text: __('You are booked for :event.', ['event' => $this->event->title]));
        $this->refresh();
    }

    public function decline(AnswerBookingRequest $answer): void
    {
        $answer->decline($this->bookingForReply());

        Flux::toast(text: __('Request declined.'));
        $this->refresh();
    }

    public function render(): mixed
    {
        return view('livewire.events.show')->title($this->event->title);
    }

    /**
     * Whether it can still be answered is the action's call, so a request that expired while the
     * page was open gets a message rather than an error page.
     */
    private function bookingForReply(): BookingRequest
    {
        $booking = $this->booking;

        abort_if($booking === null, 403);

        return $booking->setRelation('event', $this->event);
    }

    private function refresh(): void
    {
        $this->event->refresh();
        unset($this->booking, $this->lineUp);
    }

    private function user(): User
    {
        /** @var User */
        return Auth::user();
    }
}
