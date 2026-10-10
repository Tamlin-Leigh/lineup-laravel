{{--
    Who is playing, how many bands the event needs, then the bands free on the date to ask. Ticking
    stops once the open band slots are used up (the server checks the same limit on save).
--}}
@php $taken = $this->takenBandSlots(); @endphp

@include('partials.event-form.line-up-list')

<x-profile-section
    data-test="book-bands"
    x-data="{
        taken: {{ $taken }},
        open() { return Math.max(0, (parseInt($wire.form.band_slots) || 0) - this.taken) },
        left() { return Math.max(0, this.open() - $wire.bandIds.length) },
    }"
>
    <x-slot:heading>
        <span class="flex items-center justify-between gap-2">
            {{ __('Book Bands') }}
            <span class="text-xs font-semibold text-zinc-500" x-show="$wire.bandIds.length" x-cloak>
                <span x-text="$wire.bandIds.length"></span> {{ __('selected') }}
            </span>
        </span>
    </x-slot:heading>

    <div class="grid grid-cols-1 items-end gap-3 sm:grid-cols-[10rem_1fr]">
        <flux:input
            wire:model="form.band_slots"
            type="number"
            min="{{ max(1, $taken) }}"
            max="{{ App\Livewire\Forms\EventForm::MAX_BAND_SLOTS }}"
            step="1"
            :label="__('Bands playing')"
            required
            data-test="band-slots"
        />
        <flux:text size="sm" class="pb-2.5">
            @if (auth()->user()->role === App\Enums\UserRole::Band && ($event === null || $event->user_id === auth()->id()))
                {{ __('Include your own band: it takes one of these slots.') }}
            @endif
            <span x-text="left()"></span>
            <span x-text="left() === 1 ? @js(__('slot left to fill.')) : @js(__('slots left to fill.'))"></span>
        </flux:text>
    </div>

    <flux:text>
        {{ $freeOn
            ? __('Bands :free. Each one you tick is asked to play; bands that decline free up their slot again.', ['free' => $freeOn])
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
                <x-bookable-account :account="$freeBand" model="bandIds" limited wire:key="free-band-{{ $freeBand->id }}" data-test="free-band-{{ $freeBand->id }}" />
            @endforeach
        </div>
    @endif

    <flux:error name="bandIds" />
</x-profile-section>
