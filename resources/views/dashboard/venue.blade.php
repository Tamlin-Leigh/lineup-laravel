@php use App\Enums\SoundEngineer; @endphp

<div class="grid grid-cols-1 gap-5 md:grid-cols-2">
    <x-profile-section :title="$user->name">
        <p class="text-zinc-900 dark:text-white">{{ __('Capacity: :count', ['count' => number_format($venue->capacity)]) }}</p>
        <p class="text-zinc-900 dark:text-white">{{ $venue->location }}</p>
        <p class="text-zinc-900 dark:text-white">{{ __('Minimum door charge: R:amount', ['amount' => number_format($venue->min_door_charge)]) }}</p>
    </x-profile-section>

    <x-profile-section :title="__('Sound & Equipment')">
        <p class="text-zinc-900 dark:text-white">
            {{ __('Sound engineer: :option', ['option' => $venue->sound_engineer->label()]) }}
            @if ($venue->sound_engineer === SoundEngineer::InHouse && $venue->sound_engineer_name)
                ({{ $venue->sound_engineer_name }})
            @endif
        </p>
        <p class="text-zinc-900 dark:text-white">{{ $venue->has_own_equipment ? __('Has own equipment') : __('No own equipment') }}</p>
        @if ($venue->has_own_equipment && $venue->equipment_details)
            <flux:text class="whitespace-pre-line">{{ $venue->equipment_details }}</flux:text>
        @endif
    </x-profile-section>

    <x-profile-section :title="__('Manager Contact')">
        <p class="text-zinc-900 dark:text-white">{{ $venue->manager_name }}</p>
        <flux:link href="tel:{{ $venue->manager_phone }}">{{ $venue->manager_phone }}</flux:link>
        <flux:link href="mailto:{{ $venue->manager_email }}">{{ $venue->manager_email }}</flux:link>
    </x-profile-section>

    <x-profile-section :title="__('Event Preferences')">
        @if ($venue->event_days->isNotEmpty())
            <div class="flex flex-wrap gap-2">
                @foreach ($venue->event_days as $day)
                    <x-pill class="px-3 py-1" title="{{ $day->description() }}">{{ $day->label() }}</x-pill>
                @endforeach
            </div>
        @else
            <flux:text>{{ __('No preferences set yet.') }}</flux:text>
        @endif
    </x-profile-section>
</div>
