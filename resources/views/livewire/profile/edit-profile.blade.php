@php use App\Enums\UserRole; @endphp

<div class="mx-auto flex w-full max-w-3xl flex-col gap-6">
    <div class="flex flex-wrap items-center justify-between gap-4">
        <div class="flex items-center gap-4">
            <livewire:profile.avatar-upload :user="$user" wire:key="avatar" />

            <div class="min-w-0">
                <div class="flex flex-wrap items-center gap-2">
                    <flux:heading size="xl" level="1">{{ $this->displayName() }}</flux:heading>
                    <x-pill>{{ $user->role->label() }}</x-pill>
                </div>
                <flux:text class="mt-1">
                    @if ($this->displayName() !== $user->name)
                        {{ $user->name }} &middot;
                    @endif
                    {{ $user->email }}
                </flux:text>
            </div>
        </div>

        <div class="flex gap-2">
            @if ($editing)
                <flux:button wire:click="cancel">{{ __('Cancel') }}</flux:button>
                <flux:button type="submit" form="profile-form" variant="primary" data-test="save-profile-button">{{ __('Save Changes') }}</flux:button>
            @else
                <flux:button wire:click="edit" icon="pencil-square" data-test="edit-profile-button">{{ __('Edit Profile') }}</flux:button>
            @endif
        </div>
    </div>

    <form id="profile-form" wire:submit="save">
        @switch($user->role)
            @case(UserRole::Band)
                @include('profile.band', ['band' => $user->bandProfile])
                @break

            @case(UserRole::Venue)
                @include('profile.venue', ['venue' => $user->venueProfile])
                @break

            @case(UserRole::Organiser)
                @include('profile.organiser', ['organiser' => $user->organiserProfile])
                @break
        @endswitch
    </form>
</div>
