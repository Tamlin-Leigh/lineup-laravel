{{-- Who is playing, then the bands free on the date to ask. --}}
@include('partials.event-form.line-up-list')

<x-profile-section data-test="book-bands">
    <x-slot:heading>
        <span class="flex items-center justify-between gap-2">
            {{ __('Book Bands') }}
            <span class="text-xs font-semibold text-zinc-500" x-show="$wire.bandIds.length" x-cloak>
                <span x-text="$wire.bandIds.length"></span> {{ __('selected') }}
            </span>
        </span>
    </x-slot:heading>

    <flux:text>
        {{ $freeOn
            ? __('Bands :free. Tick as many as you like, or none.', ['free' => $freeOn])
            : __('Pick a date to see which bands are free.') }}
    </flux:text>

    @if ($this->freeBands->isNotEmpty() || $bandSearch !== '')
        <flux:input wire:model.live.debounce.400ms="bandSearch" type="search" icon="magnifying-glass" :placeholder="__('Search by band name or genre')" :aria-label="__('Search bands')" />
    @endif

    @if ($this->freeBands->isEmpty())
        <div class="rounded-xl border border-dashed border-zinc-300 p-5 text-center text-sm text-zinc-500">
            {{ $bandSearch !== '' ? __('No free bands match your search.') : __('No bands have marked themselves free on this date yet.') }}
        </div>
    @else
        <div class="grid grid-cols-1 gap-2 sm:grid-cols-2">
            @foreach ($this->freeBands as $freeBand)
                <x-bookable-account :account="$freeBand" model="bandIds" wire:key="free-band-{{ $freeBand->id }}" data-test="free-band-{{ $freeBand->id }}" />
            @endforeach
        </div>
    @endif
</x-profile-section>
