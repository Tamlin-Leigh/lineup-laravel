@props([
    'sidebar' => false,
])

@if($sidebar)
    <flux:sidebar.brand :name="config('app.name', 'LineUp')" {{ $attributes->class('font-extrabold') }}>
        <x-slot name="logo" class="flex aspect-square size-8 items-center justify-center rounded-md bg-brand text-white">
            <flux:icon.musical-note variant="micro" class="size-5" />
        </x-slot>
    </flux:sidebar.brand>
@else
    <flux:brand :name="config('app.name', 'LineUp')" {{ $attributes->class('font-extrabold') }}>
        <x-slot name="logo" class="flex aspect-square size-8 items-center justify-center rounded-md bg-brand text-white">
            <flux:icon.musical-note variant="micro" class="size-5" />
        </x-slot>
    </flux:brand>
@endif
