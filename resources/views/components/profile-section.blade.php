{{-- A profile card. Use the `title` prop, or a `heading` slot for a richer title. --}}
@props(['title' => null])

<section {{ $attributes->class('rounded-2xl border border-zinc-200 bg-white p-5 shadow-[0_8px_30px_rgba(0,0,0,0.04)] dark:border-zinc-700 dark:bg-zinc-900') }}>
    <flux:heading size="lg" level="2" class="mb-3">{{ $heading ?? $title }}</flux:heading>
    <div class="flex flex-col gap-3 text-sm">
        {{ $slot }}
    </div>
</section>
