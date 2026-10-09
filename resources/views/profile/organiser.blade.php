<x-profile-tab name="account" :active="$active">
    @include('profile.partials.account')
</x-profile-tab>

<x-profile-tab name="organiser" :active="$active">
    <x-profile-section :title="__('Organisation')">
        @if ($editing)
            @include('partials.profile-fields.organiser')
        @elseif ($organiser)
            <p class="text-zinc-900">{{ $organiser->org_name }}</p>
            <flux:link href="tel:{{ $organiser->contact_number }}">{{ $organiser->contact_number }}</flux:link>
            <flux:link href="mailto:{{ $organiser->contact_email }}">{{ $organiser->contact_email }}</flux:link>
        @endif
    </x-profile-section>
</x-profile-tab>

<x-profile-tab name="links" :active="$active">
    @include('profile.partials.links', ['profile' => $organiser, 'socialOnly' => true])
</x-profile-tab>
