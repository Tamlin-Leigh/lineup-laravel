{{-- An event row, laid out like the prototype's EventCard. Put buttons in the `actions` slot. --}}
@props(['event'])

@php [$from, $to] = $event->category->accent(); @endphp

<article {{ $attributes->class('flex flex-col gap-4 rounded-2xl border border-zinc-200 bg-white p-4 sm:flex-row sm:items-center dark:border-zinc-700 dark:bg-zinc-900') }}>
    <div class="h-20 w-full shrink-0 rounded-xl sm:h-16 sm:w-24" style="background: linear-gradient(135deg, {{ $from }}, {{ $to }})" aria-hidden="true"></div>

    <div class="flex min-w-0 flex-1 flex-col gap-1">
        <span class="w-fit rounded-full px-2.5 py-0.5 text-[11px] font-bold" style="background-color: color-mix(in srgb, {{ $from }} 15%, transparent); color: {{ $from }}">
            {{ $event->category->label() }}
        </span>
        <h3 class="truncate text-base font-bold text-zinc-900 dark:text-white">{{ $event->title }}</h3>
        @if ($event->description)
            <p class="truncate text-sm text-zinc-500 dark:text-zinc-400">{{ $event->description }}</p>
        @endif
    </div>

    <div class="flex min-w-0 flex-col gap-1 text-sm text-zinc-500 sm:w-56 dark:text-zinc-400">
        <span class="flex items-center gap-1.5 truncate">
            <flux:icon.map-pin variant="micro" class="shrink-0" />
            {{ $event->location }}
        </span>
        <span class="flex items-center gap-1.5 truncate">
            <flux:icon.calendar variant="micro" class="shrink-0" />
            {{ $event->starts_at->format('j M Y') }} &middot; {{ $event->starts_at->format('H:i') }}
        </span>
    </div>

    <div class="rounded-full bg-brand/10 px-3 py-1.5 text-center text-sm font-extrabold text-brand sm:w-24 dark:bg-brand-secondary/20 dark:text-brand-tertiary">
        {{ $event->priceLabel() }}
    </div>

    @isset($actions)
        <div class="flex shrink-0 gap-1">{{ $actions }}</div>
    @endisset
</article>
