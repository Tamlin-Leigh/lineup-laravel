{{-- Link repeater bound to ProfileForm. The host component needs addLink() / removeLink() (see InteractsWithProfileForm). --}}
@props([
    'field',
    'links',
    'kind',
    'label',
    'addLabel',
])

<div class="flex flex-col gap-2" role="group" aria-labelledby="{{ $field }}-label">
    <flux:label id="{{ $field }}-label">{{ $label }}</flux:label>

    @foreach ($links as $index => $link)
        <div wire:key="{{ $field }}-{{ $index }}">
            <div class="flex items-center gap-2">
                {{-- Follows the platform picked in the select straight away (wire:model updates $wire in the browser). --}}
                <span class="grid size-10 shrink-0 place-items-center rounded-lg border border-zinc-200 bg-white">
                    @foreach ($kind->platforms() as $platform)
                        <x-platform-icon
                            :platform="$platform"
                            class="size-5"
                            x-show="$wire.$get('form.{{ $field }}.{{ $index }}.platform') === '{{ $platform->value }}'"
                            :style="($link['platform'] ?? null) === $platform->value ? null : 'display: none'"
                        />
                    @endforeach
                </span>

                <flux:select wire:model="form.{{ $field }}.{{ $index }}.platform" class="max-w-36" aria-label="{{ __('Platform') }}">
                    @foreach ($kind->platforms() as $platform)
                        <flux:select.option value="{{ $platform->value }}">{{ $platform->label() }}</flux:select.option>
                    @endforeach
                </flux:select>

                <flux:input wire:model="form.{{ $field }}.{{ $index }}.url" type="url" placeholder="https://..." aria-label="{{ __('Link URL') }}" />

                <flux:button
                    type="button"
                    variant="ghost"
                    icon="x-mark"
                    wire:click="removeLink('{{ $field }}', {{ $index }})"
                    aria-label="{{ __('Remove link') }}"
                />
            </div>
            <flux:error name="form.{{ $field }}.{{ $index }}.platform" />
            <flux:error name="form.{{ $field }}.{{ $index }}.url" />
        </div>
    @endforeach

    <flux:error name="form.{{ $field }}" />

    @if (count($links) < App\Livewire\Forms\ProfileForm::MAX_LINKS)
        <button
            type="button"
            wire:click="addLink('{{ $field }}')"
            class="self-start rounded-lg border border-dashed border-zinc-300 px-3 py-2 text-sm font-medium text-brand-secondary hover:border-brand-secondary dark:border-zinc-600"
        >
            + {{ $addLabel }}
        </button>
    @endif
</div>
