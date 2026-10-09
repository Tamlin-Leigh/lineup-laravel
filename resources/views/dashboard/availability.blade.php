<x-profile-section>
    <x-slot:heading>
        <span class="flex items-center justify-between">
            {{ __('Your Availability') }}
            <span class="flex gap-3">
                <flux:link :href="route('discover')" wire:navigate class="text-xs font-semibold">{{ __('Find who\'s free') }}</flux:link>
                <flux:link :href="route('calendar')" wire:navigate class="text-xs font-semibold">{{ __('Manage') }}</flux:link>
            </span>
        </span>
    </x-slot:heading>

    @if ($upcomingFreeDays->isNotEmpty())
        <div class="flex flex-wrap gap-2">
            @foreach ($upcomingFreeDays as $date)
                <span class="rounded-full bg-emerald-100 px-3 py-1 text-xs font-bold text-emerald-800 dark:bg-emerald-900/50 dark:text-emerald-200">{{ $date->format('D j M') }}</span>
            @endforeach
        </div>
        @if ($freeDayCount > $upcomingFreeDays->count())
            <flux:text size="sm">{{ __('+ :count more free days', ['count' => $freeDayCount - $upcomingFreeDays->count()]) }}</flux:text>
        @endif
    @else
        <flux:text>
            {{ __('Mark the days you are free so others can find you for a gig.') }}
            <flux:link :href="route('calendar')" wire:navigate>{{ __('Open calendar') }}</flux:link>
        </flux:text>
    @endif
</x-profile-section>
