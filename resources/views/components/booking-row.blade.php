{{--
    One booking request. On a received request (`for="received"`) you see whose event it is; on a
    sent one (`for="sent"`) you see the band or venue you asked. Put buttons in the `actions` slot.
--}}
@props(['booking', 'for' => 'received', 'showEvent' => true])

@php
    $event = $booking->event;
    $other = $for === 'received' ? $event->owner : $booking->recipient;
@endphp

<article {{ $attributes->class('flex flex-col gap-3 rounded-2xl border border-zinc-200 bg-white p-4 sm:flex-row sm:items-center') }}>
    <a href="{{ route('profiles.show', $other) }}" wire:navigate class="flex min-w-0 flex-1 items-center gap-3 hover:no-underline">
        <x-user-avatar :user="$other" class="size-11 text-sm" />
        <div class="min-w-0">
            <div class="flex flex-wrap items-center gap-2">
                <span class="truncate font-bold text-zinc-900">{{ $other->displayName() }}</span>
                <x-pill>{{ $other->role->label() }}</x-pill>
            </div>
            @if ($showEvent)
                <p class="truncate text-sm text-zinc-500">
                    {{ $event->title }} &middot; {{ $event->starts_at->format('D j M Y, H:i') }} &middot; {{ $event->location }}
                </p>
            @endif
            @if ($for === 'received' && filled($booking->message))
                <p class="mt-1 text-sm text-zinc-700 italic">“{{ $booking->message }}”</p>
            @endif
        </div>
    </a>

    <div class="flex shrink-0 flex-wrap items-center gap-2">
        <x-booking-status :booking="$booking" />
        @if ($booking->isAwaitingReply())
            <span class="text-xs text-zinc-500" title="{{ $booking->expires_at->format('D j M Y, H:i') }}">
                {{ __('Reply by :date', ['date' => $booking->expires_at->format('D j M, H:i')]) }}
            </span>
        @endif
        {{ $actions ?? '' }}
    </div>
</article>
