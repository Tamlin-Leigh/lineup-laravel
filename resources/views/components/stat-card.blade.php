@props([
    'label',
    'value',
    'icon',
])

<div {{ $attributes->class('flex items-center gap-3 rounded-2xl border border-zinc-200 bg-white p-4 shadow-[0_8px_30px_rgba(0,0,0,0.04)] dark:border-zinc-700 dark:bg-zinc-900') }}>
    <span class="grid size-10 shrink-0 place-items-center rounded-xl bg-brand/12 text-brand dark:bg-brand-secondary/20 dark:text-brand-tertiary">
        <flux:icon :icon="$icon" class="size-5" />
    </span>
    <div>
        <p class="text-xs font-semibold text-zinc-500 dark:text-zinc-400">{{ $label }}</p>
        <p class="text-xl font-extrabold text-zinc-900 dark:text-white">{{ $value }}</p>
    </div>
</div>
