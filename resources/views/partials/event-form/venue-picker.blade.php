{{--
    The venues free on the event's date (one can be asked at a time). In the new event wizard
    (`$withElsewhere`), "Somewhere else" is the alternative and asks for a location instead.
--}}
@php
    $withElsewhere ??= false;
    $ownRegion = $this->ownRegion();
    $listRegion = $this->venueListRegion();
@endphp

<x-profile-section data-test="book-venue">
    <x-slot:heading>{{ $withElsewhere ? __('Where is it?') : __('Book a Venue') }}</x-slot:heading>

    <flux:text>
        {{ $freeOn
            ? __('Venues :free. The one you pick is asked to host your event; if they decline or don\'t reply, you can ask another.', ['free' => $freeOn])
            : __('Pick a date to see which venues are free.') }}
    </flux:text>

    {{-- Venues near the host by default; touring hosts can look further afield. --}}
    <div class="flex flex-col gap-3" data-test="venue-region">
        @if ($ownRegion)
            <flux:switch wire:model.live="localVenuesOnly" :label="__('Only venues in your region (:region)', ['region' => __($ownRegion->label())])" :description="__('Turn this off to find venues in another region, e.g. for a tour.')" data-test="local-venues-only" />
        @endif

        @if (! $ownRegion || ! $localVenuesOnly)
            <flux:select wire:model.live="venueRegionFilter" :label="__('Show venues in')" data-test="venue-region-select">
                <flux:select.option value="">{{ __('All regions') }}</flux:select.option>
                @foreach (App\Enums\Region::options() as $value => $label)
                    <flux:select.option value="{{ $value }}">{{ __($label) }}</flux:select.option>
                @endforeach
            </flux:select>
        @endif
    </div>

    @if ($this->freeVenues->isNotEmpty() || $venueSearch !== '')
        <flux:input wire:model.live.debounce.400ms="venueSearch" type="search" icon="magnifying-glass" :placeholder="__('Search by venue name or location')" :aria-label="__('Search venues')" />
    @endif

    @if ($this->freeVenues->isEmpty())
        <div class="rounded-xl border border-dashed border-zinc-300 p-5 text-center text-sm text-zinc-500">
            @if ($venueSearch !== '')
                {{ __('No free venues match your search.') }}
            @elseif ($listRegion)
                {{ __('No venues in :region have marked themselves free on this date yet. Try another region.', ['region' => __($listRegion->label())]) }}
            @else
                {{ __('No venues have marked themselves free on this date yet.') }}
            @endif
        </div>
    @endif

    <div class="grid grid-cols-1 gap-2 sm:grid-cols-2">
        @foreach ($this->freeVenues as $freeVenue)
            {{-- On the edit page the artists are listed below, and depend on the venue's region. --}}
            <x-bookable-account :account="$freeVenue" type="radio" model="venueId" :live="! $withElsewhere" wire:key="free-venue-{{ $freeVenue->id }}" data-test="free-venue-{{ $freeVenue->id }}" />
        @endforeach

        @if ($withElsewhere)
            <label class="flex cursor-pointer items-center gap-3 rounded-xl border border-zinc-200 bg-white p-3 transition has-checked:border-brand has-checked:bg-brand/5" data-test="venue-elsewhere">
                <input type="radio" wire:model="venueId" value="" class="size-4 accent-brand" />
                <span class="grid size-10 shrink-0 place-items-center rounded-full bg-zinc-100 text-zinc-500">
                    <flux:icon.map-pin variant="mini" />
                </span>
                <span class="min-w-0 flex-1">
                    <span class="block font-bold text-zinc-900">{{ __('Somewhere else') }}</span>
                    <span class="block text-xs text-zinc-500">{{ __('Not a LineUp venue') }}</span>
                </span>
            </label>
        @endif
    </div>

    @if ($withElsewhere)
        <div x-show="! $wire.venueId">
            {{-- Not `required`: the box is only hidden when a venue is picked, and a hidden required
                 field silently blocks the browser from submitting. The server checks it for "somewhere else". --}}
            <flux:input wire:model="form.location" :label="__('Location')" :placeholder="__('Venue name and city')" />
        </div>
    @elseif ($this->freeVenues->isNotEmpty())
        <div x-show="$wire.venueId" x-cloak>
            <flux:button size="sm" variant="ghost" x-on:click="$wire.set('venueId', '')">{{ __('Clear venue') }}</flux:button>
        </div>
    @endif
</x-profile-section>
