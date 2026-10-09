<div class="flex flex-col gap-3" role="group" aria-label="{{ __('Event days') }}">
    @foreach (App\Enums\EventDay::cases() as $option)
        <x-choice-card
            model="form.event_days"
            :value="$option->value"
            multiple
            :label="$option->label()"
            :description="$option->description()"
        />
    @endforeach
    <flux:error name="form.event_days" />
</div>
