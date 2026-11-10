{{--
    An artist or venue that can be picked for a booking request on the event form. `type` is
    "checkbox" for artists (pick several) or "radio" for venues (an event has one venue). `limited`
    stops unticked boxes being ticked once the surrounding x-data's left() reaches zero (it also
    needs a picked(id) there).
--}}
@props(['account', 'type' => 'checkbox', 'model', 'limited' => false])

@php
    $detail = $account->artistProfile
        ? $account->artistProfile->genre.' · '.$account->artistProfile->bookingTerms()
        : ($account->venueProfile
            ? $account->venueProfile->location.' · '.__('Capacity :count', ['count' => number_format($account->venueProfile->capacity)])
            : '');
@endphp

<label {{ $attributes->class('flex cursor-pointer items-center gap-3 rounded-xl border border-zinc-200 bg-white p-3 transition has-checked:border-brand has-checked:bg-brand/5 has-disabled:cursor-not-allowed has-disabled:opacity-50') }}>
    <input
        type="{{ $type }}"
        wire:model="{{ $model }}"
        value="{{ $account->id }}"
        class="size-4 accent-brand"
        {{-- picked() rather than $el.checked: the latter isn't reactive, so after a re-render (e.g. a search) ticked boxes got disabled too. --}}
        @if ($limited) x-bind:disabled="! picked({{ $account->id }}) && left() === 0" @endif
    />
    <x-user-avatar :user="$account" class="size-10 text-xs" />
    <span class="min-w-0 flex-1">
        <span class="block truncate font-bold text-zinc-900">{{ $account->displayName() }}</span>
        <span class="block truncate text-xs text-zinc-500">{{ $detail }}</span>
    </span>
    <a href="{{ route('profiles.show', $account) }}" target="_blank" class="text-xs font-semibold text-brand" @click.stop>{{ __('Profile') }}</a>
</label>
