@php
    $owner = $event->owner;
    $booking = $this->booking;
    $openSlots = $event->openArtistSlots();
@endphp

<div class="mx-auto flex w-full max-w-3xl flex-col gap-6">
    <div>
        <flux:link :href="url()->previous() !== url()->current() ? url()->previous() : route('bookings.index')" class="text-sm">&larr; {{ __('Back') }}</flux:link>
    </div>

    <div class="flex flex-wrap items-center gap-4">
        <x-event-date :event="$event" class="size-16! rounded-xl [&>span:last-child]:text-2xl" />
        <div class="min-w-0 flex-1">
            <div class="flex flex-wrap items-center gap-2">
                <flux:heading size="xl" level="1">{{ $event->title }}</flux:heading>
                @if ($event->status !== App\Enums\EventStatus::Published)
                    <x-pill data-test="event-status">{{ $event->status->label() }}</x-pill>
                @endif
            </div>
            <flux:text class="mt-1">{{ $event->starts_at->format('l j F Y, H:i') }}</flux:text>
        </div>
        @can('update', $event)
            <flux:button :href="route('events.edit', $event)" wire:navigate icon="pencil-square">{{ __('Edit') }}</flux:button>
        @endcan
    </div>

    @if ($booking)
        <x-profile-section :title="__('Your booking request')" data-test="your-booking">
            <div class="flex flex-wrap items-center gap-2">
                <x-booking-status :booking="$booking" />
                @if ($booking->isAwaitingReply())
                    <span class="text-sm text-zinc-500">{{ __('Reply by :date', ['date' => $booking->expires_at->format('D j M, H:i')]) }}</span>
                @endif
            </div>
            @if (filled($booking->message))
                <p class="text-sm text-zinc-700 italic">“{{ $booking->message }}”</p>
            @endif
            <flux:error name="booking" />
            @can('respond', $booking)
                <div class="flex gap-2">
                    <flux:button variant="primary" wire:click="accept" data-test="accept-booking">{{ __('Accept') }}</flux:button>
                    <flux:button wire:click="decline" wire:confirm="{{ __('Decline this booking request?') }}" data-test="decline-booking">{{ __('Decline') }}</flux:button>
                </div>
            @endcan
        </x-profile-section>
    @endif

    <div class="grid grid-cols-1 gap-5 md:grid-cols-2">
        <x-profile-section :title="__('Details')" data-test="event-details">
            <div class="flex gap-2 text-zinc-900">
                <flux:icon.map-pin variant="micro" class="mt-1 shrink-0 text-zinc-500" />
                <div class="min-w-0">
                    @if ($event->venue)
                        <flux:link :href="route('profiles.show', $event->venue->user)" wire:navigate>{{ $event->venue->user->displayName() }}</flux:link>
                        <p class="text-sm text-zinc-500">{{ $event->location }}</p>
                    @else
                        {{ $event->location }}
                    @endif
                </div>
            </div>
            <p class="flex items-center gap-2 text-zinc-900">
                <flux:icon.ticket variant="micro" class="shrink-0 text-zinc-500" />
                {{ __('Entry: :price', ['price' => $event->priceLabel()]) }}
            </p>
            <p class="flex items-center gap-2 text-zinc-900">
                <flux:icon.musical-note variant="micro" class="shrink-0 text-zinc-500" />
                {{ trans_choice(':count artist playing|:count artists playing', $event->artist_slots) }}
                @if ($openSlots > 0)
                    <span class="text-zinc-500">&middot; {{ trans_choice(':count slot still open|:count slots still open', $openSlots) }}</span>
                @endif
            </p>
        </x-profile-section>

        <x-profile-section :title="__('Hosted by')">
            <a href="{{ route('profiles.show', $owner) }}" wire:navigate class="flex items-center gap-3 hover:no-underline" data-test="event-host">
                <x-user-avatar :user="$owner" class="size-11 text-sm" />
                <span class="min-w-0">
                    <span class="block truncate font-bold text-zinc-900">{{ $owner->displayName() }}</span>
                    <x-pill>{{ $owner->role->label() }}</x-pill>
                </span>
            </a>
        </x-profile-section>
    </div>

    @if (filled($event->description))
        <x-profile-section :title="__('About the event')">
            <flux:text class="whitespace-pre-line">{{ $event->description }}</flux:text>
        </x-profile-section>
    @endif

    <x-profile-section :title="__('Line-up')" data-test="line-up">
        @forelse ($this->lineUp as $performer)
            <a href="{{ route('profiles.show', $performer) }}" wire:navigate class="flex items-center gap-3 rounded-xl border border-zinc-200 bg-white p-3 hover:no-underline" wire:key="line-up-{{ $performer->id }}">
                <x-user-avatar :user="$performer" class="size-10 text-xs" />
                <span class="min-w-0 flex-1">
                    <span class="block truncate font-bold text-zinc-900">{{ $performer->displayName() }}</span>
                    <span class="block truncate text-xs text-zinc-500">{{ $performer->artistProfile?->genre }}</span>
                </span>
                <x-pill>{{ $performer->id === $event->user_id ? __('Host') : __('Booked') }}</x-pill>
            </a>
        @empty
            <flux:text>{{ __('No artists confirmed yet.') }}</flux:text>
        @endforelse

        @if ($this->invitedArtists->isNotEmpty())
            <flux:text class="mt-2 font-semibold" data-test="invited-artists">{{ __('Invited, waiting for a reply') }}</flux:text>
            @foreach ($this->invitedArtists as $invited)
                <a href="{{ route('profiles.show', $invited) }}" wire:navigate class="flex items-center gap-3 rounded-xl border border-dashed border-zinc-200 bg-white p-3 hover:no-underline" wire:key="invited-{{ $invited->id }}">
                    <x-user-avatar :user="$invited" class="size-10 text-xs" />
                    <span class="min-w-0 flex-1">
                        <span class="block truncate font-bold text-zinc-900">{{ $invited->displayName() }}</span>
                        <span class="block truncate text-xs text-zinc-500">{{ $invited->artistProfile?->genre }}</span>
                    </span>
                    <x-pill>{{ __('Invited') }}</x-pill>
                </a>
            @endforeach
        @endif
    </x-profile-section>
</div>
