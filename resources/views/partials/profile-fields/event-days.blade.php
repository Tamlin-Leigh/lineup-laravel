<div class="flex flex-col gap-3" role="group" aria-label="{{ __('Event days') }}">
    @foreach (App\Enums\EventDay::cases() as $option)
        <x-choice-card
            wire:click="toggleEventDay('{{ $option->value }}')"
            checkbox
            :label="$option->label()"
            :description="$option->description()"
            :selected="in_array($option->value, $form->event_days, true)"
        />
    @endforeach
    <flux:error name="form.event_days" />
</div>
