<div class="flex flex-col gap-5">
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
        <x-stat-card :label="__('Booked Before Year End')" :value="$bookedBeforeYearEnd" icon="calendar" />
        <x-stat-card :label="__('Events Completed')" :value="$completedEvents" icon="check-circle" />
    </div>

    @include('dashboard.upcoming-events')

    <x-profile-section :title="__('Tags From Past Events')">
        @if ($pastCategoryCounts->isNotEmpty())
            <div class="flex flex-wrap gap-2">
                @foreach ($pastCategoryCounts as $category => $count)
                    <x-pill class="px-3 py-1">{{ $category }} &middot; {{ $count }}</x-pill>
                @endforeach
            </div>
        @else
            <flux:text>{{ __('No completed events yet.') }}</flux:text>
        @endif
    </x-profile-section>

    <x-profile-section :title="__('Your Band')">
        <p class="font-semibold text-zinc-900 dark:text-white">{{ $band->band_name }}</p>
        <flux:text>{{ trans_choice(':count member|:count members', $band->member_count) }} &middot; {{ $band->genre }}</flux:text>
        <div>
            <flux:link :href="route('profile.show')" wire:navigate class="text-sm">{{ __('Edit profile') }}</flux:link>
        </div>
    </x-profile-section>
</div>
