{{--
    Who is playing, how many artists the event needs, then the artists free on the date to ask. Ticking
    stops once the open artist slots are used up, and lowering the number of artists unticks the latest
    picks that no longer fit (the server checks the same limit on save).
--}}
@php $taken = $this->takenArtistSlots(); @endphp

@include('partials.event-form.line-up-list')

<x-profile-section
    data-test="book-artists"
    x-data="{
        taken: {{ $taken }},
        open() { return Math.max(0, (parseInt($wire.form.artist_slots) || 0) - this.taken) },
        left() { return Math.max(0, this.open() - $wire.artistIds.length) },
        picked(id) { return $wire.artistIds.map(String).includes(String(id)) },
        // Runs once the number is committed (not per keystroke, so typing 12 doesn't pass through 1).
        fitPicks() {
            if (parseInt($wire.form.artist_slots) > 0 && $wire.artistIds.length > this.open()) {
                $wire.artistIds = $wire.artistIds.slice(0, this.open())
            }
        },
    }"
>
    <x-slot:heading>
        <span class="flex items-center justify-between gap-2">
            {{ __('Book Artists') }}
            <span class="text-xs font-semibold text-zinc-500" x-show="$wire.artistIds.length" x-cloak>
                <span x-text="$wire.artistIds.length"></span> {{ __('selected') }}
            </span>
        </span>
    </x-slot:heading>

    <div class="grid grid-cols-1 items-end gap-3 sm:grid-cols-[10rem_1fr]">
        <flux:input
            wire:model="form.artist_slots"
            type="number"
            min="{{ max(1, $taken) }}"
            max="{{ App\Livewire\Forms\EventForm::MAX_ARTIST_SLOTS }}"
            step="1"
            :label="__('Artists playing')"
            x-on:change="fitPicks()"
            required
            data-test="artist-slots"
        />
        <flux:text size="sm" class="pb-2.5">
            @if (auth()->user()->role === App\Enums\UserRole::Artist && ($event === null || $event->user_id === auth()->id()))
                {{ __('Include yourself: you take one of these slots.') }}
            @endif
            <span x-text="left()"></span>
            <span x-text="left() === 1 ? @js(__('slot left to fill.')) : @js(__('slots left to fill.'))"></span>
        </flux:text>
    </div>

    <flux:text>
        {{ $freeOn
            ? __('Artists :free. Each one you tick is asked to play; artists that decline free up their slot again.', ['free' => $freeOn])
            : __('Pick a date to see which artists are free.') }}
    </flux:text>

    @if ($this->freeArtists->isNotEmpty() || $artistSearch !== '')
        <flux:input wire:model.live.debounce.400ms="artistSearch" type="search" icon="magnifying-glass" :placeholder="__('Search by artist name or genre')" :aria-label="__('Search artists')" />
    @endif

    @if ($this->freeArtists->isEmpty())
        <div class="rounded-xl border border-dashed border-zinc-300 p-5 text-center text-sm text-zinc-500">
            {{ $artistSearch !== '' ? __('No free artists match your search.') : __('No artists have marked themselves free on this date yet.') }}
        </div>
    @else
        <div class="grid grid-cols-1 gap-2 sm:grid-cols-2">
            @foreach ($this->freeArtists as $freeArtist)
                <x-bookable-account :account="$freeArtist" model="artistIds" limited wire:key="free-artist-{{ $freeArtist->id }}" data-test="free-artist-{{ $freeArtist->id }}" />
            @endforeach
        </div>
    @endif

    <flux:error name="artistIds" />
</x-profile-section>
