{{-- A platform's brand logo in its brand colour (Simple Icons), or a generic icon for Website / Other. --}}
@props(['platform'])

@php
    /** @var \App\Enums\LinkPlatform $platform */
    $brandIcon = $platform->brandIcon();
@endphp

<span {{ $attributes->merge(['style' => 'color: '.$platform->color()])->class('inline-grid shrink-0 place-items-center') }} aria-hidden="true">
    @if ($brandIcon)
        {{-- svg() loads the icon file by name; the <x-si-*> component aliases depend on a directory scan
             that can miss files on a Windows bind mount. --}}
        {{ svg($brandIcon, 'size-full fill-current') }}
    @else
        <flux:icon :icon="$platform->fallbackIcon()" class="size-full" />
    @endif
</span>
