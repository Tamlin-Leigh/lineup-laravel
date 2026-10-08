{{-- Event stats and lists are empty until events and bookings exist (phases 5 and 6). --}}
<div class="flex flex-col gap-5">
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
        <x-stat-card :label="__('Booked Before Year End')" :value="0" icon="calendar" />
        <x-stat-card :label="__('Events Completed')" :value="0" icon="check-circle" />
    </div>

    <x-profile-section :title="__('Upcoming Events')">
        <flux:text>{{ __('No upcoming events booked yet.') }}</flux:text>
    </x-profile-section>

    <x-profile-section :title="__('Tags From Past Events')">
        <flux:text>{{ __('No completed events yet.') }}</flux:text>
    </x-profile-section>

    <x-profile-section :title="__('Your Band')">
        <p class="font-semibold text-zinc-900 dark:text-white">{{ $band->band_name }}</p>
        <flux:text>{{ trans_choice(':count member|:count members', $band->member_count) }} &middot; {{ $band->genre }}</flux:text>
        <div>
            <flux:link :href="route('profile.show')" wire:navigate class="text-sm">{{ __('Edit profile') }}</flux:link>
        </div>
    </x-profile-section>
</div>
