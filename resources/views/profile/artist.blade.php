@php
    $split = fn (?string $list) => str($list)->explode(',')->map(fn ($item) => trim($item))->filter()->values();
    $genres = $split($artist?->genre);
    $members = $split($artist?->member_names);
@endphp

<x-profile-tab name="account" :active="$active">
    @include('profile.partials.account')
</x-profile-tab>

<x-profile-tab name="artist" :active="$active">
    @if ($editing)
        <x-profile-section :title="__('Artist Profile')">
            @include('partials.profile-fields.artist')
        </x-profile-section>
    @else
        <x-profile-section>
            <x-slot:heading>
                <span class="flex flex-wrap items-center gap-2">
                    {{ $artist?->artist_name }}
                    @foreach ($genres as $genre)
                        <x-pill>{{ $genre }}</x-pill>
                    @endforeach
                </span>
            </x-slot:heading>

            @if ($artist?->bio)
                <p class="whitespace-pre-line text-zinc-900">{{ $artist->bio }}</p>
            @else
                <flux:text>{{ __('No bio added yet.') }}</flux:text>
            @endif
        </x-profile-section>
    @endif
</x-profile-tab>

<x-profile-tab name="members" :active="$active">
    @if ($editing)
        <x-profile-section :title="__('Members')">
            @include('partials.profile-fields.members')
        </x-profile-section>
    @else
        <x-profile-section :title="__('Members (:count)', ['count' => $artist?->member_count ?? 0])">
            @if ($members->isNotEmpty())
                <ul class="flex flex-col gap-1.5 text-zinc-900">
                    @foreach ($members as $member)
                        <li>{{ $member }}</li>
                    @endforeach
                </ul>
            @else
                <flux:text>{{ __('No members listed yet.') }}</flux:text>
            @endif
        </x-profile-section>
    @endif
</x-profile-tab>

<x-profile-tab name="manager" :active="$active">
    @include('profile.partials.manager', ['profile' => $artist])
</x-profile-tab>

<x-profile-tab name="links" :active="$active">
    @include('profile.partials.links', ['profile' => $artist])
</x-profile-tab>
