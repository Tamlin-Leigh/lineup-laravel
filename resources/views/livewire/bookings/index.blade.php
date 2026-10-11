@php
    $user = auth()->user();
    $tab = $this->activeTab();
    ['awaiting' => $awaiting, 'confirmed' => $confirmed, 'closed' => $closed] = $this->bookings;
@endphp

<div class="mx-auto flex w-full max-w-4xl flex-col gap-5">
    <div>
        <flux:heading size="xl" level="1">{{ __('Bookings') }}</flux:heading>
        <flux:text class="mt-1">
            {{ $tab === 'received'
                ? __('Requests to take part in other people\'s events. Accept or decline before the deadline, or the request expires.')
                : __('Requests you have sent for your events. To book someone, open one of your events and pick from who is free that day.') }}
        </flux:text>
    </div>

    @if ($user->canReceiveBookingRequests())
        <div class="flex gap-2" role="tablist">
            @foreach (['received' => __('Received'), 'sent' => __('Sent')] as $value => $label)
                <button
                    type="button"
                    role="tab"
                    aria-selected="{{ $tab === $value ? 'true' : 'false' }}"
                    wire:click="$set('tab', '{{ $value }}')"
                    @class([
                        'rounded-full px-4 py-2 text-sm font-bold transition',
                        'bg-brand text-white' => $tab === $value,
                        'border border-zinc-200 bg-white text-zinc-500 hover:text-zinc-900' => $tab !== $value,
                    ])
                    data-test="bookings-tab-{{ $value }}"
                >
                    {{ $label }}
                </button>
            @endforeach
        </div>
    @endif

    <flux:error name="booking" />

    <x-profile-section :title="$tab === 'received' ? __('Waiting for your reply') : __('Waiting for a reply')">
        @forelse ($awaiting as $booking)
            <x-booking-row :booking="$booking" :for="$tab" wire:key="awaiting-{{ $booking->id }}">
                @can('respond', $booking)
                    <x-slot:actions>
                        <flux:button size="sm" variant="primary" wire:click="accept({{ $booking->id }})" data-test="accept-booking-{{ $booking->id }}">{{ __('Accept') }}</flux:button>
                        <flux:button size="sm" wire:click="decline({{ $booking->id }})" wire:confirm="{{ __('Decline this booking request?') }}" data-test="decline-booking-{{ $booking->id }}">{{ __('Decline') }}</flux:button>
                    </x-slot:actions>
                @endcan
            </x-booking-row>
        @empty
            <flux:text>
                @if ($tab === 'received')
                    {{ __('No requests right now. Keep your free days up to date so others can find you.') }}
                    <flux:link :href="route('calendar')" wire:navigate>{{ __('Open calendar') }}</flux:link>
                @else
                    {{ __('Nothing waiting. Open one of your events to book who is free that day.') }}
                    <flux:link :href="route('events.index')" wire:navigate>{{ __('Your events') }}</flux:link>
                @endif
            </flux:text>
        @endforelse
    </x-profile-section>

    <x-profile-section :title="__('Confirmed')">
        @forelse ($confirmed as $booking)
            <x-booking-row :booking="$booking" :for="$tab" wire:key="confirmed-{{ $booking->id }}">
                <x-slot:actions>
                    <flux:button size="sm" icon="chat-bubble-left-right" :href="route('bookings.chat', $booking)" wire:navigate data-test="message-booking-{{ $booking->id }}">{{ __('Message') }}</flux:button>
                </x-slot:actions>
            </x-booking-row>
        @empty
            <flux:text>{{ __('No confirmed bookings coming up.') }}</flux:text>
        @endforelse
    </x-profile-section>

    @if ($closed->isNotEmpty())
        <x-profile-section :title="__('History')">
            @foreach ($closed as $booking)
                <x-booking-row :booking="$booking" :for="$tab" wire:key="closed-{{ $booking->id }}">
                    {{-- A decline or no-reply frees the slot: offer to ask someone else while there's still time. --}}
                    @if ($tab === 'sent'
                        && in_array($booking->currentStatus(), [App\Enums\BookingStatus::Declined, App\Enums\BookingStatus::Expired], true)
                        && $user->can('sendBookingRequests', $booking->event))
                        <x-slot:actions>
                            <flux:button size="sm" :href="route('events.edit', $booking->event)" wire:navigate data-test="replace-booking-{{ $booking->id }}">
                                {{ __('Find a replacement') }}
                            </flux:button>
                        </x-slot:actions>
                    @endif
                </x-booking-row>
            @endforeach
        </x-profile-section>
    @endif
</div>
