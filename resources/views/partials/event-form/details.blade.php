{{-- Title and description, then tickets and status. --}}
@php use App\Enums\EventStatus; @endphp

<x-profile-section :title="__('Details')">
    <flux:input wire:model="form.title" :label="__('Title')" required :placeholder="__('e.g. Headline Show - The Loft')" />

    <flux:textarea wire:model="form.description" :label="__('Description (optional)')" rows="3" :placeholder="__('What should people know about this event?')" />
</x-profile-section>

<x-profile-section :title="__('Tickets & Status')">
    <flux:input wire:model="form.price" :label="__('Ticket Price (ZAR)')" type="number" min="0" step="1" required :description="__('Use 0 for a free event.')" />

    @php
        $canPublish = $this->canPublish();
        $blockers = $canPublish ? [] : $this->publishBlockers();
    @endphp

    <flux:radio.group wire:model="form.status" :label="__('Status')">
        <flux:radio value="{{ EventStatus::Draft->value }}" :label="__('Draft')" :description="__('Pending: only you can see it until the line-up and venue are confirmed.')" />
        <flux:radio
            value="{{ EventStatus::Published->value }}"
            :label="__('Published')"
            :description="$canPublish ? __('Shown on your profile, and as active until it has happened.') : __('Available once everyone has confirmed.')"
            :disabled="! $canPublish"
            data-test="status-published"
        />
        @if ($event)
            <flux:radio value="{{ EventStatus::Cancelled->value }}" :label="__('Cancelled')" :description="__('Kept for your records but taken off the calendar.')" />
        @endif
    </flux:radio.group>

    @if ($blockers !== [])
        <ul class="flex flex-col gap-1 rounded-xl bg-amber-50 p-3 text-sm text-amber-900" data-test="publish-blockers">
            @foreach ($blockers as $blocker)
                <li class="flex items-start gap-2">
                    <flux:icon.clock variant="micro" class="mt-0.5 shrink-0" />
                    {{ $blocker }}
                </li>
            @endforeach
        </ul>
    @endif
</x-profile-section>
