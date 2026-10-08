@php
    use App\Enums\EventCategory;
    use App\Enums\EventStatus;
@endphp

<div class="mx-auto flex w-full max-w-2xl flex-col gap-5">
    <div>
        <flux:text size="sm">
            <flux:link :href="route('events.index')" wire:navigate>{{ __('Events') }}</flux:link> / {{ $event ? __('Edit') : __('New') }}
        </flux:text>
        <flux:heading size="xl" level="1">{{ $event ? $event->title : __('New Event') }}</flux:heading>
    </div>

    <form wire:submit="save" class="flex flex-col gap-5">
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

        <x-profile-section :title="__('When & Where')">
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <flux:input wire:model="form.date" :label="__('Date')" type="date" required />
                <flux:input wire:model="form.time" :label="__('Start Time')" type="time" required />
            </div>

            <flux:select wire:model.live="form.venue_profile_id" :label="__('Venue on BandLink (optional)')">
                <flux:select.option value="">{{ __('Not a BandLink venue') }}</flux:select.option>
                @foreach ($this->venues as $venue)
                    <flux:select.option value="{{ $venue->id }}">{{ $venue->user->name }} &ndash; {{ $venue->location }}</flux:select.option>
                @endforeach
            </flux:select>

            <flux:input wire:model="form.location" :label="__('Location')" required :placeholder="__('Venue name and city')" />
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

        <div class="flex justify-end gap-2">
            <flux:button :href="route('events.index')" wire:navigate>{{ __('Cancel') }}</flux:button>
            <flux:button type="submit" variant="primary" data-test="save-event-button">
                {{ $event ? __('Save Changes') : __('Create Event') }}
            </flux:button>
        </div>
    </form>
</div>
