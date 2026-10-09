@php
    use App\Enums\LinkKind;
    use App\Enums\SoundEngineer;
    use App\Enums\UserRole;

    $venue = $user->venueProfile;
    $band = $user->bandProfile;
    $organiser = $user->organiserProfile;
    $links = ($band ?? $organiser)?->links ?? collect();
    $members = str($band?->member_names)->explode(',')->map(fn ($name) => trim($name))->filter();
@endphp

{{-- Read-only profile seen by other users. Contact details (email, phone, manager contact) are deliberately left out. --}}
<x-layouts::app :title="$user->displayName()">
    <div class="mx-auto flex w-full max-w-3xl flex-col gap-6">
        <div>
            <flux:link :href="url()->previous() !== url()->current() ? url()->previous() : route('discover')" class="text-sm">&larr; {{ __('Back') }}</flux:link>
        </div>

        <div class="flex flex-wrap items-center gap-4">
            <x-user-avatar :user="$user" class="size-16 text-lg" />
            <div class="min-w-0">
                <div class="flex flex-wrap items-center gap-2">
                    <flux:heading size="xl" level="1">{{ $user->displayName() }}</flux:heading>
                    <x-pill>{{ $user->role->label() }}</x-pill>
                </div>
                <flux:text class="mt-1">
                    @switch($user->role)
                        @case(UserRole::Venue) {{ $venue?->location }} @break
                        @case(UserRole::Band) {{ $band?->genre }} @break
                        @default {{ $user->name }}
                    @endswitch
                </flux:text>
            </div>
        </div>

        <x-profile-section :title="__('Free for a gig')">
            @if ($freeDays->isNotEmpty())
                <div class="flex flex-wrap gap-2">
                    @foreach ($freeDays as $date)
                        <span class="rounded-full bg-emerald-100 px-3 py-1 text-xs font-bold text-emerald-800">{{ $date->format('D j M') }}</span>
                    @endforeach
                </div>
            @else
                <flux:text>{{ __('No free days marked at the moment.') }}</flux:text>
            @endif
        </x-profile-section>

        @if ($venue)
            <div class="grid grid-cols-1 gap-5 md:grid-cols-2">
                <x-profile-section :title="__('Venue Details')">
                    <p class="text-zinc-900">{{ $venue->location }}</p>
                    <p class="text-zinc-900">{{ __('Capacity: :count', ['count' => number_format($venue->capacity)]) }}</p>
                    <p class="text-zinc-900">{{ __('Minimum door charge: R:amount', ['amount' => number_format($venue->min_door_charge)]) }}</p>
                </x-profile-section>

                <x-profile-section :title="__('Sound & Equipment')">
                    <p class="text-zinc-900">
                        {{ __('Sound engineer: :option', ['option' => $venue->sound_engineer->label()]) }}
                        @if ($venue->sound_engineer === SoundEngineer::InHouse && $venue->sound_engineer_name)
                            ({{ $venue->sound_engineer_name }})
                        @endif
                    </p>
                    <p class="text-zinc-900">{{ $venue->has_own_equipment ? __('Has own equipment') : __('No own equipment') }}</p>
                    @if ($venue->has_own_equipment && $venue->equipment_details)
                        <flux:text class="whitespace-pre-line">{{ $venue->equipment_details }}</flux:text>
                    @endif
                </x-profile-section>

                @if ($venue->event_days->isNotEmpty())
                    <x-profile-section :title="__('Usually hosts')">
                        <div class="flex flex-wrap gap-2">
                            @foreach ($venue->event_days as $day)
                                <x-pill class="px-3 py-1">{{ $day->label() }} &middot; {{ $day->description() }}</x-pill>
                            @endforeach
                        </div>
                    </x-profile-section>
                @endif
            </div>
        @endif

        @if ($band)
            <div class="grid grid-cols-1 gap-5 md:grid-cols-2">
                <x-profile-section :title="__('About')">
                    @if ($band->bio)
                        <p class="whitespace-pre-line text-zinc-900">{{ $band->bio }}</p>
                    @else
                        <flux:text>{{ __('No bio added yet.') }}</flux:text>
                    @endif
                </x-profile-section>

                <x-profile-section :title="__('Members (:count)', ['count' => $band->member_count])">
                    <ul class="flex flex-col gap-1.5 text-zinc-900">
                        @foreach ($members as $member)
                            <li>{{ $member }}</li>
                        @endforeach
                    </ul>
                </x-profile-section>
            </div>
        @endif

        @if ($organiser)
            <x-profile-section :title="__('Organisation')">
                <p class="text-zinc-900">{{ $organiser->org_name }}</p>
            </x-profile-section>
        @endif

        @if ($events->isNotEmpty())
            <x-profile-section :title="__('Upcoming Events')">
                @foreach ($events as $event)
                    @php [$fromColour, $toColour] = $event->category->accent(); @endphp
                    <div class="flex items-center gap-3">
                        <span class="size-10 shrink-0 rounded-lg" style="background: linear-gradient(135deg, {{ $fromColour }}, {{ $toColour }})" aria-hidden="true"></span>
                        <div class="min-w-0 flex-1">
                            <p class="truncate font-bold text-zinc-900">{{ $event->title }}</p>
                            <p class="truncate text-xs text-zinc-500">{{ $event->location }}</p>
                        </div>
                        <span class="shrink-0 text-xs font-semibold text-zinc-500">{{ $event->starts_at->format('j M Y') }}</span>
                    </div>
                @endforeach
            </x-profile-section>
        @endif

        @if ($links->isNotEmpty())
            <x-profile-section :title="__('Links')">
                @foreach (LinkKind::cases() as $kind)
                    @php $ofKind = $links->where('kind', $kind); @endphp
                    @if ($ofKind->isNotEmpty())
                        <div class="flex flex-col gap-2">
                            <span class="text-xs font-bold tracking-wide text-zinc-500 uppercase">{{ $kind->label() }}</span>
                            @foreach ($ofKind as $link)
                                <div class="flex items-center gap-2.5">
                                    <x-platform-icon :platform="$link->platform" class="size-5" />
                                    <flux:link href="{{ $link->url }}" target="_blank" rel="noopener noreferrer">{{ $link->platform->label() }}</flux:link>
                                </div>
                            @endforeach
                        </div>
                    @endif
                @endforeach
            </x-profile-section>
        @endif
    </div>
</x-layouts::app>
