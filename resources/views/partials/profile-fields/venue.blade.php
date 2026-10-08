@php use App\Enums\SoundEngineer; @endphp

<flux:input wire:model="form.capacity" :label="__('Venue Capacity')" type="number" min="1" required :placeholder="__('Maximum number of people')" />

<flux:input wire:model="form.location" :label="__('Venue Location')" required autocomplete="street-address" :placeholder="__('Enter venue address')" />

<flux:input wire:model="form.min_door_charge" :label="__('Minimum Door Charge (ZAR)')" type="number" min="0" step="1" required placeholder="0" />

<div class="flex flex-col gap-2" role="group" aria-labelledby="sound-engineer-label">
    <flux:label id="sound-engineer-label">{{ __('Sound Engineer') }}</flux:label>
    @foreach (SoundEngineer::cases() as $option)
        <x-choice-card
            wire:click="$set('form.sound_engineer', '{{ $option->value }}')"
            :label="$option->label()"
            :selected="$form->sound_engineer === $option->value"
        />
    @endforeach
    <flux:error name="form.sound_engineer" />
</div>

@if ($form->sound_engineer === SoundEngineer::InHouse->value)
    <flux:input wire:model="form.sound_engineer_name" :label="__('Sound Engineer Name')" required :placeholder="__('Enter sound engineer\'s name')" />
@endif

<div class="flex flex-col gap-2" role="group" aria-labelledby="equipment-label">
    <flux:label id="equipment-label">{{ __('Do you have your own equipment?') }}</flux:label>
    <flux:text size="sm" class="-mt-1">{{ __('Drum kit, amps, PA system, etc.') }}</flux:text>
    <div class="flex gap-2">
        <x-choice-card wire:click="$set('form.has_own_equipment', true)" class="text-center" :label="__('Yes')" :selected="$form->has_own_equipment === true" />
        <x-choice-card wire:click="$set('form.has_own_equipment', false)" class="text-center" :label="__('No')" :selected="$form->has_own_equipment === false" />
    </div>
    <flux:error name="form.has_own_equipment" />
</div>

@if ($form->has_own_equipment)
    <flux:textarea wire:model="form.equipment_details" :label="__('Equipment Details (optional)')" rows="3" :placeholder="__('List available equipment...')" />
@endif
