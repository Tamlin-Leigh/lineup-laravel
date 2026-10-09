<div class="grid grid-cols-1 gap-5 md:grid-cols-2">
    @include('profile.partials.account')

    <x-profile-section :title="__('Organiser Details')">
        @if ($editing)
            @include('partials.profile-fields.organiser')
        @elseif ($organiser)
            <p class="text-zinc-900 dark:text-white">{{ $organiser->org_name }}</p>
            <flux:link href="tel:{{ $organiser->contact_number }}">{{ $organiser->contact_number }}</flux:link>
            <flux:link href="mailto:{{ $organiser->contact_email }}">{{ $organiser->contact_email }}</flux:link>
        @endif
    </x-profile-section>

    @include('profile.partials.links', ['profile' => $organiser, 'socialOnly' => true])
</div>
