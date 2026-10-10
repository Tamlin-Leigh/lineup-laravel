{{--
    A band or venue that can be picked for a booking request on the event form. `type` is
    "checkbox" for bands (pick several) or "radio" for venues (an event has one venue).
--}}
@props(['account', 'type' => 'checkbox', 'model'])

@php
    $detail = $account->bandProfile
        ? $account->bandProfile->genre.' · '.trans_choice(':count member|:count members', $account->bandProfile->member_count)
        : ($account->venueProfile
            ? $account->venueProfile->location.' · '.__('Capacity :count', ['count' => number_format($account->venueProfile->capacity)])
            : '');
@endphp

<label {{ $attributes->class('flex cursor-pointer items-center gap-3 rounded-xl border border-zinc-200 bg-white p-3 transition has-checked:border-brand has-checked:bg-brand/5') }}>
    <input type="{{ $type }}" wire:model="{{ $model }}" value="{{ $account->id }}" class="size-4 accent-brand" />
    <x-user-avatar :user="$account" class="size-10 text-xs" />
    <span class="min-w-0 flex-1">
        <span class="block truncate font-bold text-zinc-900">{{ $account->displayName() }}</span>
        <span class="block truncate text-xs text-zinc-500">{{ $detail }}</span>
    </span>
    <a href="{{ route('profiles.show', $account) }}" target="_blank" class="text-xs font-semibold text-brand" @click.stop>{{ __('Profile') }}</a>
</label>
