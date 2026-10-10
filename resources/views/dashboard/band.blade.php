<div class="flex flex-col gap-5">
    @include('dashboard.pending-bookings')

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
        <x-stat-card :label="__('Booked Before Year End')" :value="$bookedBeforeYearEnd" icon="calendar" />
        <x-stat-card :label="__('Events Completed')" :value="$completedEvents" icon="check-circle" />
    </div>

    @include('dashboard.availability')

    @include('dashboard.upcoming-events')

    <x-profile-section :title="__('Your Band')">
        <p class="font-semibold text-zinc-900 dark:text-white">{{ $band->band_name }}</p>
        <flux:text>{{ trans_choice(':count member|:count members', $band->member_count) }} &middot; {{ $band->genre }}</flux:text>
        <div>
            <flux:link :href="route('profile.show')" wire:navigate class="text-sm">{{ __('Edit profile') }}</flux:link>
        </div>
    </x-profile-section>
</div>
