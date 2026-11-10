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

    @php $active = $this->activeTab(); @endphp

    <form id="profile-form" wire:submit="save" x-data="{ tab: $wire.entangle('tab') }" class="flex flex-col gap-5">
        {{-- The same tab bar for every role; each role's partial supplies one panel per tab. --}}
        <nav class="flex gap-5 overflow-x-auto border-b border-zinc-200" role="tablist" aria-label="{{ __('Profile sections') }}">
            @foreach ($this->tabs() as $key => $label)
                <button
                    type="button"
                    role="tab"
                    aria-controls="profile-tab-{{ $key }}"
                    x-on:click="tab = '{{ $key }}'"
                    x-bind:aria-selected="tab === '{{ $key }}'"
                    {{-- Object syntax so the inactive classes rendered by the server are removed when switching. --}}
                    x-bind:class="{ 'border-brand text-brand': tab === '{{ $key }}', 'border-transparent text-zinc-500 hover:text-zinc-900': tab !== '{{ $key }}' }"
                    @class([
                        'shrink-0 border-b-2 px-1 pb-3 text-sm font-semibold transition',
                        'border-brand text-brand' => $active === $key,
                        'border-transparent text-zinc-500 hover:text-zinc-900' => $active !== $key,
                    ])
                    data-test="profile-tab-{{ $key }}"
                >
                    {{ $label }}
                </button>
            @endforeach
        </nav>

        <div class="max-w-2xl">
            @switch($user->role)
                @case(UserRole::Artist)
                    @include('profile.artist', ['artist' => $user->artistProfile])
                    @break

                @case(UserRole::Venue)
                    @include('profile.venue', ['venue' => $user->venueProfile])
                    @break

                @case(UserRole::Organiser)
                    @include('profile.organiser', ['organiser' => $user->organiserProfile])
                    @break
            @endswitch
        </div>
    </form>
</div>
