@php use App\Enums\SoundEngineer; @endphp

<flux:input wire:model="form.capacity" :label="__('Venue Capacity')" type="number" min="1" required :placeholder="__('Maximum number of people')" />

<flux:input wire:model="form.location" :label="__('Venue Location')" required autocomplete="street-address" :placeholder="__('Enter venue address')" />

<flux:input wire:model="form.min_door_charge" :label="__('Minimum Door Charge (ZAR)')" type="number" min="0" step="1" required placeholder="0" />

<div class="flex flex-col gap-2" role="group" aria-labelledby="sound-engineer-label">
    <flux:label id="sound-engineer-label">{{ __('Sound Engineer') }}</flux:label>
    @foreach (SoundEngineer::cases() as $option)
        <x-choice-card model="form.sound_engineer" :value="$option->value" :label="$option->label()" />
    @endforeach
    <flux:error name="form.sound_engineer" />
</div>

{{-- Shown and hidden in the browser so picking an option doesn't wait on the server. --}}
<div x-show="$wire.$get('form.sound_engineer') === '{{ SoundEngineer::InHouse->value }}'" @style(['display: none' => $form->sound_engineer !== SoundEngineer::InHouse->value])>
    <flux:input wire:model="form.sound_engineer_name" :label="__('Sound Engineer Name')" :placeholder="__('Enter sound engineer\'s name')" />
</div>

<div class="flex flex-col gap-2" role="group" aria-labelledby="equipment-label">
    <flux:label id="equipment-label">{{ __('Do you have your own equipment?') }}</flux:label>
    <flux:text size="sm" class="-mt-1">{{ __('Drum kit, amps, PA system, etc.') }}</flux:text>
    <div class="flex gap-2">
        <x-choice-card model="form.has_own_equipment" :value="true" class="text-center" :label="__('Yes')" />
        <x-choice-card model="form.has_own_equipment" :value="false" class="text-center" :label="__('No')" />
    </div>
    <flux:error name="form.has_own_equipment" />
</div>

<div x-show="$wire.$get('form.has_own_equipment') === true" @style(['display: none' => $form->has_own_equipment !== true])>
    <flux:textarea wire:model="form.equipment_details" :label="__('Equipment Details (optional)')" rows="3" :placeholder="__('List available equipment...')" />
</div>
