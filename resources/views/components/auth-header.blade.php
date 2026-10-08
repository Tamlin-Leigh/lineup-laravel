@props([
    'title',
    'description',
])

<div class="flex w-full flex-col text-center">
    <h1 class="text-[28px] font-extrabold tracking-[-0.6px] text-brand-ink">{{ $title }}</h1>
    <p class="mt-2 text-[13px] text-brand-muted">{{ $description }}</p>
</div>
