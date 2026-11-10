@php use App\Enums\UserRole; @endphp

<x-layouts::app :title="__('Dashboard')">
    <div class="mx-auto flex w-full max-w-3xl flex-col gap-5">
        <div>
            <flux:heading size="xl" level="1">{{ __('Dashboard') }}</flux:heading>
            <flux:text class="mt-1">
                {{ __('Welcome back, :name', ['name' => $user->artistProfile->artist_name ?? $user->organiserProfile->org_name ?? $user->name]) }}
            </flux:text>
        </div>

        @if (! ($user->venueProfile ?? $user->artistProfile ?? $user->organiserProfile))
            <flux:callout icon="user-circle" color="sky">
                <flux:callout.heading>{{ __('Finish setting up your profile') }}</flux:callout.heading>
                <flux:callout.text>{{ __('Add your details so others can find and book you.') }}</flux:callout.text>
                <x-slot name="actions">
                    <flux:button size="sm" :href="route('profile.show')" wire:navigate>{{ __('Go to profile') }}</flux:button>
                </x-slot>
            </flux:callout>
        @else
            @switch($user->role)
                @case(UserRole::Artist)
                    @include('dashboard.artist', ['artist' => $user->artistProfile])
                    @break

                @case(UserRole::Venue)
                    @include('dashboard.venue', ['venue' => $user->venueProfile])
                    @break

                @case(UserRole::Organiser)
                    @include('dashboard.organiser', ['organiser' => $user->organiserProfile])
                    @break
            @endswitch
        @endif
    </div>
</x-layouts::app>
