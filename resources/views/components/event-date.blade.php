{{-- An event's date as a small calendar tile: month above day. --}}
@props(['event'])

<span {{ $attributes->class('grid size-10 shrink-0 place-content-center rounded-lg bg-brand/10 text-center leading-none text-brand') }} aria-hidden="true">
    <span class="text-[10px] font-bold uppercase">{{ $event->starts_at->format('M') }}</span>
    <span class="text-base font-extrabold">{{ $event->starts_at->format('j') }}</span>
</span>
