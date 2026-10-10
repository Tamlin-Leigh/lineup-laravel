<x-profile-section>
    <x-slot:heading>
        <span class="flex items-center justify-between">
            {{ __('Upcoming Events') }}
            <flux:link :href="route('events.index')" wire:navigate class="text-xs font-semibold">{{ __('View all') }}</flux:link>
        </span>
    </x-slot:heading>

    @forelse ($upcomingEvents as $event)
        <a href="{{ route('events.edit', $event) }}" wire:navigate class="flex items-center gap-3 hover:no-underline">
            <x-event-date :event="$event" />
            <span class="min-w-0 flex-1">
                <span class="block truncate font-bold text-zinc-900 dark:text-white">{{ $event->title }}</span>
                <span class="block truncate text-xs text-zinc-500 dark:text-zinc-400">{{ $event->location }}</span>
            </span>
            <span class="shrink-0 text-xs font-semibold text-zinc-500 dark:text-zinc-400">{{ $event->starts_at->format('j M Y') }}</span>
        </a>
    @empty
        <flux:text>
            {{ __('No upcoming events booked yet.') }}
            <flux:link :href="route('events.create')" wire:navigate>{{ __('Add one') }}</flux:link>
        </flux:text>
    @endforelse
</x-profile-section>
