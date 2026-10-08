@php use App\Enums\SoundEngineer; @endphp

<div class="grid grid-cols-1 gap-5 md:grid-cols-2">
    @include('profile.partials.account')

    <x-profile-section :title="__('Venue Details')">
        @if ($editing)
            @include('partials.profile-fields.venue')
        @elseif ($venue)
            <p class="text-zinc-900 dark:text-white">{{ __('Capacity: :count', ['count' => number_format($venue->capacity)]) }}</p>
            <p class="text-zinc-900 dark:text-white">{{ $venue->location }}</p>
            <p class="text-zinc-900 dark:text-white">{{ __('Minimum door charge: R:amount', ['amount' => number_format($venue->min_door_charge)]) }}</p>
            <flux:text>
                {{ __('Sound engineer: :option', ['option' => $venue->sound_engineer->label()]) }}
                @if ($venue->sound_engineer === SoundEngineer::InHouse && $venue->sound_engineer_name)
                    ({{ $venue->sound_engineer_name }})
                @endif
            </flux:text>
            <flux:text>
                {{ $venue->has_own_equipment ? __('Has own equipment') : __('No own equipment') }}
                @if ($venue->has_own_equipment && $venue->equipment_details)
                    &ndash; {{ $venue->equipment_details }}
                @endif
            </flux:text>
        @endif
    </x-profile-section>

    @include('profile.partials.manager', ['profile' => $venue])

    <x-profile-section :title="__('Event Preferences')">
        @if ($editing)
            @include('partials.profile-fields.event-days')
        @elseif ($venue)
            <div class="flex flex-wrap gap-2">
                @foreach ($venue->event_days as $day)
                    <x-pill class="px-3 py-1" title="{{ $day->description() }}">{{ $day->label() }}</x-pill>
                @endforeach
            </div>
        @endif
    </x-profile-section>
</div>
