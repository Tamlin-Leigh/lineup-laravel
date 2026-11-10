{{-- Expects $profile: a VenueProfile or ArtistProfile (or null). --}}
<x-profile-section :title="__('Manager Contact')">
    @if ($editing)
        @include('partials.profile-fields.manager')
    @elseif ($profile)
        <p class="text-zinc-900 dark:text-white">{{ $profile->manager_name }}</p>
        <flux:link href="tel:{{ $profile->manager_phone }}">{{ $profile->manager_phone }}</flux:link>
        <flux:link href="mailto:{{ $profile->manager_email }}">{{ $profile->manager_email }}</flux:link>
    @endif
</x-profile-section>
