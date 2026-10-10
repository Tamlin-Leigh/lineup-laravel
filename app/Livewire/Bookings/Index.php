<?php

namespace App\Livewire\Bookings;

use App\Actions\AnswerBookingRequest;
use App\Enums\BookingStatus;
use App\Models\BookingRequest;
use App\Models\User;
use Flux\Flux;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Requests this account has received (bands and venues answer them here) and requests it has
 * sent for its own events. Opening the page marks the account's booking notifications as read.
 */
class Index extends Component
{
    #[Url]
    public string $tab = 'received';

    public function mount(): void
    {
        $this->user()->unreadNotifications()->update(['read_at' => now()]);
    }

    /**
     * Organisers are never booked themselves, so they only have requests they've sent.
     */
    public function activeTab(): string
    {
        return $this->user()->canReceiveBookingRequests() && $this->tab !== 'sent' ? 'received' : 'sent';
    }

    public function accept(int $id, AnswerBookingRequest $answer): void
    {
        $booking = $this->findForReply($id);
        $answer->accept($booking);

        Flux::toast(variant: 'success', text: __('You are booked for :event.', ['event' => $booking->event->title]));
        unset($this->bookings);
    }

    public function decline(int $id, AnswerBookingRequest $answer): void
    {
        $booking = $this->findForReply($id);
        $answer->decline($booking);

        Flux::toast(text: __('Request declined.'));
        unset($this->bookings);
    }

    /**
     * The active tab's requests split into what needs a reply, what's confirmed, and the rest.
     *
     * @return array{awaiting: Collection<int, BookingRequest>, confirmed: Collection<int, BookingRequest>, closed: Collection<int, BookingRequest>}
     */
    #[Computed]
    public function bookings(): array
    {
        $bookings = $this->query()
            ->with(['event.owner.venueProfile', 'event.owner.bandProfile', 'event.owner.organiserProfile', 'recipient.bandProfile', 'recipient.venueProfile'])
            ->get();

        [$awaiting, $rest] = $bookings->partition(fn (BookingRequest $booking) => $booking->isAwaitingReply());
        [$confirmed, $closed] = $rest->partition(
            fn (BookingRequest $booking) => $booking->status === BookingStatus::Accepted && ! $booking->event->isPast(),
        );

        return [
            'awaiting' => $awaiting->sortBy('expires_at')->values(),
            'confirmed' => $confirmed->sortBy('event.starts_at')->values(),
            'closed' => $closed->sortByDesc('updated_at')->take(20)->values(),
        ];
    }

    public function render(): mixed
    {
        return view('livewire.bookings.index')->title(__('Bookings'));
    }

    /**
     * @return Builder<BookingRequest>
     */
    private function query(): Builder
    {
        $user = $this->user();

        return $this->activeTab() === 'received'
            ? $user->receivedBookingRequests()->getQuery()
            : BookingRequest::query()->whereRelation('event', 'user_id', $user->id);
    }

    /**
     * One of this account's own received requests. Whether it can still be answered is the
     * action's call, so a request that expired while the page was open gets a message rather
     * than an error page.
     */
    private function findForReply(int $id): BookingRequest
    {
        return $this->user()->receivedBookingRequests()->with(['event.owner', 'recipient.venueProfile'])->findOrFail($id);
    }

    private function user(): User
    {
        /** @var User */
        return Auth::user();
    }
}
