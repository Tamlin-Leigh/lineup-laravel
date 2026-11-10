@php
    use App\Enums\UserRole;

    [$from, $to] = $this->range();
    $results = $this->results;
    $rangeLabel = $from->isSameDay($to)
        ? $from->format('D j M Y')
        : $from->format('D j M').' – '.$to->format('D j M Y');
@endphp

<div class="mx-auto flex w-full max-w-5xl flex-col gap-5">
    <div>
        <flux:heading size="xl" level="1">{{ __('Discover') }}</flux:heading>
        <flux:text class="mt-1">{{ __('Find venues, artists and organisers who are free when you need them.') }}</flux:text>
    </div>

    <x-profile-section>
        <x-slot:heading>{{ __('Who and when') }}</x-slot:heading>

        <div class="flex flex-wrap gap-2" role="radiogroup" aria-label="{{ __('Who') }}">
            @foreach ($this->roleOptions() as $value => $label)
                <button
                    type="button"
                    role="radio"
                    aria-checked="{{ $role === $value ? 'true' : 'false' }}"
                    wire:click="$set('role', '{{ $value }}')"
                    @class([
                        'rounded-full px-4 py-2 text-sm font-bold transition',
                        'bg-brand text-white' => $role === $value,
                        'border border-zinc-200 bg-white text-zinc-500 hover:text-zinc-900' => $role !== $value,
                    ])
                    data-test="discover-role-{{ $value }}"
                >
                    {{ $label }}
                </button>
            @endforeach
        </div>

        <div class="grid grid-cols-1 items-end gap-4 sm:grid-cols-[auto_1fr]">
            <flux:radio.group wire:model.live="when" variant="segmented" :label="__('When')">
                <flux:radio value="weekend" :label="__('Weekend')" />
                <flux:radio value="day" :label="__('Day')" />
                <flux:radio value="range" :label="__('Date range')" />
            </flux:radio.group>

            <div>
                @switch($when)
                    @case('day')
                        <flux:input wire:model.live="day" type="date" :min="today()->format('Y-m-d')" :aria-label="__('Day')" />
                        @break

                    @case('range')
                        <div class="grid grid-cols-2 gap-2">
                            <flux:input wire:model.live="from" type="date" :min="today()->format('Y-m-d')" :aria-label="__('From')" />
                            <flux:input wire:model.live="to" type="date" :min="today()->format('Y-m-d')" :aria-label="__('To')" />
                        </div>
                        @break

                    @default
                        <flux:select wire:model.live="weekend" :aria-label="__('Weekend')">
                            @foreach ($this->weekends() as $friday)
                                <flux:select.option value="{{ $friday->format('Y-m-d') }}">
                                    {{ $friday->format('D j M') }} – {{ $friday->addDays(2)->format('D j M') }}
                                    @if ($loop->first) ({{ __('this weekend') }}) @endif
                                </flux:select.option>
                            @endforeach
                        </flux:select>
                @endswitch
            </div>
        </div>

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-[1fr_14rem]">
            <flux:input wire:model.live.debounce.400ms="search" type="search" icon="magnifying-glass" :placeholder="__('Search by name, genre or location')" :aria-label="__('Search')" />
            <flux:select wire:model.live="region" :aria-label="__('Region')" data-test="discover-region">
                <flux:select.option value="">{{ __('All regions') }}</flux:select.option>
                @foreach (App\Enums\Region::options() as $value => $label)
                    <flux:select.option value="{{ $value }}">{{ __($label) }}</flux:select.option>
                @endforeach
            </flux:select>
        </div>
    </x-profile-section>

    <div class="flex flex-wrap items-baseline justify-between gap-2">
        <flux:heading size="lg" level="2">
            {{ trans_choice(':count free on :range|:count free on :range', $results->total(), ['count' => $results->total(), 'range' => $rangeLabel]) }}
        </flux:heading>
        <flux:link :href="route('calendar')" wire:navigate class="text-sm">{{ __('Mark your own free days') }}</flux:link>
    </div>

    @if ($results->isEmpty())
        <div class="rounded-2xl border border-zinc-200 bg-white p-8 text-center text-sm text-zinc-500">
            {{ __('Nobody matching your filters has marked themselves free on those days yet. Try another weekend or a wider date range.') }}
        </div>
    @else
        <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
            @foreach ($results as $account)
                <a
                    href="{{ route('profiles.show', $account) }}"
                    wire:navigate
                    wire:key="account-{{ $account->id }}"
                    class="flex flex-col gap-3 rounded-2xl border border-zinc-200 bg-white p-4 shadow-[0_8px_30px_rgba(0,0,0,0.04)] transition hover:border-brand-secondary hover:no-underline"
                >
                    <div class="flex items-center gap-3">
                        <x-user-avatar :user="$account" class="size-12 text-sm" />
                        <div class="min-w-0 flex-1">
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="truncate font-bold text-zinc-900">{{ $account->displayName() }}</span>
                                <x-pill>{{ $account->role->label() }}</x-pill>
                            </div>
                            <p class="truncate text-sm text-zinc-500">
                                @switch($account->role)
                                    @case(UserRole::Venue)
                                        {{ $account->venueProfile?->location }}
                                        @if ($account->venueProfile) &middot; {{ __('Capacity :count', ['count' => number_format($account->venueProfile->capacity)]) }} @endif
                                        @if ($account->venueProfile?->region) &middot; {{ __($account->venueProfile->region->label()) }} @endif
                                        @break
                                    @case(UserRole::Artist)
                                        {{ $account->artistProfile?->genre }}
                                        @if ($account->artistProfile) &middot; {{ trans_choice(':count member|:count members', $account->artistProfile->member_count) }} @endif
                                        @if ($account->artistProfile?->region) &middot; {{ __($account->artistProfile->region->label()) }} @endif
                                        @break
                                    @default
                                        {{ $account->name }}
                                @endswitch
                            </p>
                        </div>
                    </div>

                    <div class="flex flex-wrap gap-1.5" aria-label="{{ __('Free days') }}">
                        @foreach ($account->availabilities as $availability)
                            <span class="rounded-full bg-emerald-100 px-2.5 py-0.5 text-xs font-bold text-emerald-800">{{ $availability->date->format('D j M') }}</span>
                        @endforeach
                    </div>

                    @if ($account->availability_updated_at)
                        <p class="text-xs text-zinc-500">{{ __('Availability updated :when', ['when' => $account->availability_updated_at->diffForHumans()]) }}</p>
                    @endif
                </a>
            @endforeach
        </div>

        @if ($results->hasPages())
            <flux:pagination :paginator="$results" />
        @endif
    @endif
</div>
