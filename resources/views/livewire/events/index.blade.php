@php
    use App\Models\Event;

    $results = $this->results;
@endphp

<div class="mx-auto flex w-full max-w-5xl flex-col gap-5">
    <div class="flex flex-wrap items-end justify-between gap-3">
        <div>
            <flux:text size="sm">{{ __('Dashboard') }} / {{ __('Events') }}</flux:text>
            <flux:heading size="xl" level="1">{{ __('Events') }}</flux:heading>
        </div>

        <flux:button variant="primary" icon="plus" :href="route('events.create')" wire:navigate data-test="new-event-button">
            {{ __('New Event') }}
        </flux:button>
    </div>

    <div class="flex flex-wrap items-center justify-between gap-3">
        <div class="flex flex-wrap gap-2" role="tablist">
            @foreach (Event::TABS as $key => $label)
                <button
                    type="button"
                    role="tab"
                    aria-selected="{{ $tab === $key ? 'true' : 'false' }}"
                    wire:click="$set('tab', '{{ $key }}')"
                    @class([
                        'rounded-full px-4 py-2 text-sm font-bold transition',
                        'bg-brand text-white' => $tab === $key,
                        'border border-zinc-200 bg-white text-zinc-500 hover:text-zinc-900 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-400 dark:hover:text-white' => $tab !== $key,
                    ])
                >
                    {{ __($label) }} ({{ $this->counts[$key] }})
                </button>
            @endforeach
        </div>

        <div class="flex flex-wrap items-center gap-2">
            <flux:input wire:model.live.debounce.300ms="search" type="search" icon="magnifying-glass" :placeholder="__('Search event, location, etc')" class="w-56" aria-label="{{ __('Search events') }}" />
        </div>
    </div>

    <div class="flex flex-col gap-3">
        @forelse ($results as $event)
            <x-event-card :event="$event" wire:key="event-{{ $event->id }}">
                <x-slot:actions>
                    <flux:button size="sm" variant="ghost" icon="pencil-square" :href="route('events.edit', $event)" wire:navigate aria-label="{{ __('Edit :title', ['title' => $event->title]) }}" />
                    <flux:button
                        size="sm"
                        variant="ghost"
                        icon="trash"
                        wire:click="delete({{ $event->id }})"
                        wire:confirm="{{ __('Delete :title? This cannot be undone.', ['title' => $event->title]) }}"
                        aria-label="{{ __('Delete :title', ['title' => $event->title]) }}"
                    />
                </x-slot:actions>
            </x-event-card>
        @empty
            <div class="rounded-2xl border border-zinc-200 bg-white p-8 text-center text-sm text-zinc-500 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-400">
                @if ($search !== '')
                    {{ __('No events match your search.') }}
                @else
                    {{ __('No events here yet.') }}
                @endif
            </div>
        @endforelse
    </div>

    @if ($results->total() > 0)
        <div class="flex flex-wrap items-center justify-between gap-3">
            <flux:text>{{ __('Showing :count out of :total', ['count' => $results->count(), 'total' => $results->total()]) }}</flux:text>
            <flux:pagination :paginator="$results" />
        </div>
    @endif
</div>
