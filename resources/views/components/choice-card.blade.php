{{-- A selectable card button. Pass `checkbox` to show a tick box (for multi-select groups). --}}
@props([
    'label',
    'description' => null,
    'selected' => false,
    'checkbox' => false,
])

<button
    type="button"
    aria-pressed="{{ $selected ? 'true' : 'false' }}"
    {{ $attributes->class([
        'w-full rounded-lg border p-4 text-left transition',
        'border-brand bg-brand/8 ring-2 ring-brand' => $selected,
        'border-zinc-300 bg-white hover:border-brand-secondary' => ! $selected,
    ]) }}
>
    <span class="flex items-center gap-3">
        @if ($checkbox)
            <span @class([
                'grid size-5 shrink-0 place-items-center rounded border-2 transition',
                'border-brand bg-brand' => $selected,
                'border-zinc-300' => ! $selected,
            ])>
                @if ($selected)
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="3" stroke="white" class="size-3" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                    </svg>
                @endif
            </span>
        @endif

        <span>
            <span class="block font-bold text-brand-ink">{{ $label }}</span>
            @if ($description)
                <span class="mt-0.5 block text-sm text-brand-muted">{{ $description }}</span>
            @endif
        </span>
    </span>
</button>
