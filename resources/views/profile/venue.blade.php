@php use App\Enums\SoundEngineer; @endphp

<x-profile-tab name="account" :active="$active">
    @include('profile.partials.account')
</x-profile-tab>

<x-profile-tab name="venue" :active="$active">
    @if ($editing)
        <x-profile-section :title="__('Venue Details')">
            @include('partials.profile-fields.venue')
        </x-profile-section>
    @elseif ($venue)
        <x-profile-section :title="__('Venue Details')">
            <p class="text-zinc-900">{{ __('Capacity: :count', ['count' => number_format($venue->capacity)]) }}</p>
            <p class="text-zinc-900">{{ $venue->location }}</p>
            <p class="text-zinc-900">{{ __('Minimum door charge: R:amount', ['amount' => number_format($venue->min_door_charge)]) }}</p>
        </x-profile-section>

        <x-profile-section :title="__('Sound & Equipment')">
            <p class="text-zinc-900">
                {{ __('Sound engineer: :option', ['option' => $venue->sound_engineer->label()]) }}
                @if ($venue->sound_engineer === SoundEngineer::InHouse && $venue->sound_engineer_name)
                    ({{ $venue->sound_engineer_name }})
                @endif
            </p>
            <p class="text-zinc-900">{{ $venue->has_own_equipment ? __('Has own equipment') : __('No own equipment') }}</p>
            @if ($venue->has_own_equipment && $venue->equipment_details)
                <flux:text class="whitespace-pre-line">{{ $venue->equipment_details }}</flux:text>
            @endif
        </x-profile-section>
    @endif
</x-profile-tab>

<x-profile-tab name="manager" :active="$active">
    @include('profile.partials.manager', ['profile' => $venue])
</x-profile-tab>

<x-profile-tab name="event-days" :active="$active">
    <x-profile-section :title="__('Event Preferences')">
        @if ($editing)
            <flux:text>{{ __('When do you prefer to host events? You can select both options.') }}</flux:text>
            @include('partials.profile-fields.event-days')
        @elseif ($venue && $venue->event_days->isNotEmpty())
            <div class="flex flex-wrap gap-2">
                @foreach ($venue->event_days as $day)
                    <x-pill class="px-3 py-1" title="{{ $day->description() }}">{{ $day->label() }}</x-pill>
                @endforeach
            </div>
        @else
            <flux:text>{{ __('No preferences set yet.') }}</flux:text>
        @endif
    </x-profile-section>
</x-profile-tab>
