{{-- Title, category and description, then tickets and status. --}}
@php
    use App\Enums\EventCategory;
    use App\Enums\EventStatus;
@endphp

<x-profile-section :title="__('Details')">
    <flux:input wire:model="form.title" :label="__('Title')" required :placeholder="__('e.g. Headline Show - The Loft')" />

    <flux:select wire:model="form.category" :label="__('Category')" required>
        <flux:select.option value="">{{ __('Choose a category') }}</flux:select.option>
        @foreach (EventCategory::cases() as $option)
            <flux:select.option value="{{ $option->value }}">{{ $option->label() }}</flux:select.option>
        @endforeach
    </flux:select>

    <flux:textarea wire:model="form.description" :label="__('Description (optional)')" rows="3" :placeholder="__('What should people know about this event?')" />
</x-profile-section>

<x-profile-section :title="__('Tickets & Status')">
    <flux:input wire:model="form.price" :label="__('Ticket Price (ZAR)')" type="number" min="0" step="1" required :description="__('Use 0 for a free event.')" />

    <flux:radio.group wire:model="form.status" :label="__('Status')">
        <flux:radio value="{{ EventStatus::Draft->value }}" :label="__('Draft')" :description="__('Only you can see it.')" />
        <flux:radio value="{{ EventStatus::Published->value }}" :label="__('Published')" :description="__('Shown as active until it has happened.')" />
        @if ($event)
            <flux:radio value="{{ EventStatus::Cancelled->value }}" :label="__('Cancelled')" :description="__('Kept for your records but taken off the calendar.')" />
        @endif
    </flux:radio.group>
</x-profile-section>
