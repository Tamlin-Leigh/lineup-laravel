@php
    use App\Livewire\Profile\EditProfile;

    $split = fn (?string $list) => str($list)->explode(',')->map(fn ($item) => trim($item))->filter()->values();
    $genres = $split($band?->genre);
    $members = $split($band?->member_names);
@endphp

<div x-data="{ tab: $wire.entangle('tab') }" class="flex flex-col gap-5">
    <nav class="flex gap-5 overflow-x-auto border-b border-zinc-200 dark:border-zinc-700" role="tablist">
        @foreach (EditProfile::BAND_TABS as $key => $label)
            <button
                type="button"
                role="tab"
                x-on:click="tab = '{{ $key }}'"
                x-bind:aria-selected="tab === '{{ $key }}'"
                x-bind:class="tab === '{{ $key }}'
                    ? 'border-brand text-brand dark:border-brand-tertiary dark:text-brand-tertiary'
                    : 'border-transparent text-zinc-500 hover:text-zinc-900 dark:text-zinc-400 dark:hover:text-white'"
                class="shrink-0 border-b-2 px-1 pb-3 text-sm font-semibold transition"
            >
                {{ __($label) }}
            </button>
        @endforeach
    </nav>

    <div class="max-w-2xl">
        <div x-show="tab === 'account'" @style(['display: none' => $tab !== 'account'])>
            @include('profile.partials.account')
        </div>

        <div x-show="tab === 'band'" @style(['display: none' => $tab !== 'band'])>
            @if ($editing)
                <x-profile-section :title="__('Band Profile')">
                    @include('partials.profile-fields.band')
                </x-profile-section>
            @else
                <x-profile-section>
                    <x-slot:heading>
                        <span class="flex flex-wrap items-center gap-2">
                            {{ $band?->band_name }}
                            @foreach ($genres as $genre)
                                <x-pill>{{ $genre }}</x-pill>
                            @endforeach
                        </span>
                    </x-slot:heading>

                    @if ($band?->bio)
                        <p class="whitespace-pre-line text-zinc-900 dark:text-white">{{ $band->bio }}</p>
                    @else
                        <flux:text>{{ __('No bio added yet.') }}</flux:text>
                    @endif
                </x-profile-section>
            @endif
        </div>

        <div x-show="tab === 'members'" @style(['display: none' => $tab !== 'members'])>
            @if ($editing)
                <x-profile-section :title="__('Members')">
                    @include('partials.profile-fields.members')
                </x-profile-section>
            @else
                <x-profile-section :title="__('Members (:count)', ['count' => $band?->member_count ?? 0])">
                    @if ($members->isNotEmpty())
                        <ul class="flex flex-col gap-1.5 text-zinc-900 dark:text-white">
                            @foreach ($members as $member)
                                <li>{{ $member }}</li>
                            @endforeach
                        </ul>
                    @else
                        <flux:text>{{ __('No members listed yet.') }}</flux:text>
                    @endif
                </x-profile-section>
            @endif
        </div>

        <div x-show="tab === 'manager'" @style(['display: none' => $tab !== 'manager'])>
            @include('profile.partials.manager', ['profile' => $band])
        </div>

        <div x-show="tab === 'links'" @style(['display: none' => $tab !== 'links'])>
            @include('profile.partials.links', ['profile' => $band])
        </div>
    </div>
</div>
