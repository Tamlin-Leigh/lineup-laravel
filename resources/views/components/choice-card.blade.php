{{--
    A selectable card bound to a Livewire property. Picking a card only updates the value in the
    browser ($wire.$set(..., false)); it is sent with the next request (e.g. Continue / Save), so
    choosing feels instant instead of waiting for a round trip.

    model:    the Livewire property, e.g. "role" or "form.sound_engineer"
    value:    the value this card represents (string or bool)
    multiple: the property is a list and the card toggles its value in or out (shows a tick box)
--}}
@props([
    'model',
    'value',
    'label',
    'description' => null,
    'multiple' => false,
])

@php
    $current = data_get(app('livewire')->current(), $model);
    $selected = $multiple ? in_array($value, (array) $current, true) : $current === $value;

    $value = Js::from($value);
    $isSelected = $multiple
        ? "(\$wire.\$get('{$model}') ?? []).includes({$value})"
        : "\$wire.\$get('{$model}') === {$value}";
    $select = $multiple
        ? "\$wire.\$set('{$model}', (\$wire.\$get('{$model}') ?? []).includes({$value}) ? (\$wire.\$get('{$model}') ?? []).filter(v => v !== {$value}) : [...(\$wire.\$get('{$model}') ?? []), {$value}], false)"
        : "\$wire.\$set('{$model}', {$value}, false)";

    $on = 'border-brand bg-brand/8 ring-2 ring-brand dark:border-brand-secondary dark:bg-brand/40 dark:ring-brand-secondary';
    $off = 'border-zinc-300 bg-white hover:border-brand-secondary dark:border-zinc-600 dark:bg-zinc-800';
    $tickOn = 'border-brand bg-brand';
    $tickOff = 'border-zinc-300';
@endphp

<button
    type="button"
    x-data
    x-on:click="{{ $select }}"
    x-bind:aria-pressed="{{ $isSelected }} ? 'true' : 'false'"
    x-bind:class="{ '{{ $on }}': {{ $isSelected }}, '{{ $off }}': ! ({{ $isSelected }}) }"
    aria-pressed="{{ $selected ? 'true' : 'false' }}"
    {{ $attributes->class(['w-full rounded-lg border p-4 text-left transition', $on => $selected, $off => ! $selected]) }}
>
    <span class="flex items-center gap-3">
        @if ($multiple)
            <span
                x-bind:class="{ '{{ $tickOn }}': {{ $isSelected }}, '{{ $tickOff }}': ! ({{ $isSelected }}) }"
                @class(['grid size-5 shrink-0 place-items-center rounded border-2 transition', $tickOn => $selected, $tickOff => ! $selected])
            >
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="3" stroke="white" class="size-3" aria-hidden="true"
                    x-show="{{ $isSelected }}" @style(['display: none' => ! $selected])>
                    <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                </svg>
            </span>
        @endif

        <span>
            <span class="block font-bold text-brand-ink dark:text-white">{{ $label }}</span>
            @if ($description)
                <span class="mt-0.5 block text-sm text-brand-muted dark:text-zinc-400">{{ $description }}</span>
            @endif
        </span>
    </span>
</button>
