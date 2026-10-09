{{-- A user's profile photo, or their initials on a brand-coloured circle. --}}
@props(['user'])

<span {{ $attributes->class('grid shrink-0 place-items-center overflow-hidden rounded-full bg-brand font-bold text-white') }}>
    @if ($photo = $user->profilePhotoUrl())
        <img src="{{ $photo }}" alt="" class="size-full object-cover" />
    @else
        {{ $user->initials() }}
    @endif
</span>
