{{-- The optional message sent with the booking requests; shown once someone is picked. --}}
<div x-show="$wire.artistIds.length || $wire.venueId" x-cloak>
    <x-profile-section :title="__('Booking Request')">
        <flux:text>
            {{ __('Everyone you picked gets an email and a request on their Bookings page, with :days days to accept or decline (or until the event, if sooner).', ['days' => App\Models\BookingRequest::REPLY_DAYS]) }}
        </flux:text>
        <flux:textarea wire:model="bookingMessage" :label="__('Message (optional)')" rows="2" :placeholder="__('e.g. Looking for a 45 minute set; we provide the PA.')" />
    </x-profile-section>
</div>
