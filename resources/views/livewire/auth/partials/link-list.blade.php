{{-- Link repeater for the sign-up flow. Expects $field, $kind (LinkKind), $label and $addLabel. --}}
<div class="flex flex-col gap-2" role="group" aria-labelledby="{{ $field }}-label">
    <flux:label id="{{ $field }}-label">{{ $label }}</flux:label>

    @foreach ($this->{$field} as $index => $link)
        <div wire:key="{{ $field }}-{{ $index }}">
            <div class="flex gap-2">
                <flux:select wire:model="{{ $field }}.{{ $index }}.platform" class="max-w-36" aria-label="{{ __('Platform') }}">
                    @foreach ($kind->platforms() as $platform)
                        <flux:select.option value="{{ $platform->value }}">{{ $platform->label() }}</flux:select.option>
                    @endforeach
                </flux:select>

                <flux:input wire:model="{{ $field }}.{{ $index }}.url" type="url" placeholder="https://..." aria-label="{{ __('Link URL') }}" />

                <flux:button
                    type="button"
                    variant="ghost"
                    icon="x-mark"
                    wire:click="removeLink('{{ $field }}', {{ $index }})"
                    aria-label="{{ __('Remove link') }}"
                />
            </div>
            <flux:error name="{{ $field }}.{{ $index }}.platform" />
            <flux:error name="{{ $field }}.{{ $index }}.url" />
        </div>
    @endforeach

    <flux:error name="{{ $field }}" />

    <button
        type="button"
        wire:click="addLink('{{ $field }}')"
        class="self-start rounded-lg border border-dashed border-zinc-300 px-3 py-2 text-sm font-medium text-brand-secondary hover:border-brand-secondary"
    >
        + {{ $addLabel }}
    </button>
</div>
