@php
    use App\Enums\EventStatus;

    $month = $this->visibleMonth();
    $todayKey = now()->format('Y-m-d');
    $eventsByDay = $this->eventsByDay;
    $busyDays = $this->busyDays;
    $pendingByDay = $this->pendingByDay;
    $freeDays = $this->freeDays;
    $pendingFreeDays = $this->freeDaysWithPendingRequests();
@endphp

<div class="mx-auto flex w-full max-w-6xl flex-col gap-5 lg:flex-row lg:items-start">
    <div class="flex min-w-0 flex-1 flex-col gap-5">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div class="flex items-center gap-3">
                <flux:button icon="chevron-left" wire:click="previousMonth" aria-label="{{ __('Previous month') }}" />
                <flux:heading size="xl" level="1" class="min-w-48 text-center">{{ $month->format('F Y') }}</flux:heading>
                <flux:button icon="chevron-right" wire:click="nextMonth" aria-label="{{ __('Next month') }}" />
                @unless ($month->isSameMonth(now()))
                    <flux:button size="sm" variant="ghost" wire:click="today">{{ __('Today') }}</flux:button>
                @endunless
            </div>

            <div class="flex flex-wrap gap-2">
                <flux:button
                    :variant="$markingAvailability ? 'filled' : 'outline'"
                    icon="{{ $markingAvailability ? 'check' : 'hand-raised' }}"
                    wire:click="$toggle('markingAvailability')"
                    aria-pressed="{{ $markingAvailability ? 'true' : 'false' }}"
                    data-test="mark-availability-button"
                >
                    {{ $markingAvailability ? __('Done') : __('Mark Availability') }}
                </flux:button>

                <flux:button
                    variant="primary"
                    icon="plus"
                    :href="route('events.create', ['date' => $month->isSameMonth(now()) ? $todayKey : $month->format('Y-m-d')])"
                    wire:navigate
                >
                    {{ __('Add Event') }}
                </flux:button>
            </div>
        </div>

        @if ($markingAvailability)
            <div class="flex flex-wrap items-center justify-between gap-3 rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 dark:border-emerald-900 dark:bg-emerald-950/40">
                <p class="text-sm text-emerald-900 dark:text-emerald-200">
                    {{ __('Click the days you are free for a gig. Click again to clear a day.') }}
                </p>
                <div class="flex flex-wrap gap-2">
                    <flux:button size="sm" wire:click="markWeekends">{{ __('Mark all weekends') }}</flux:button>
                    <flux:modal.trigger :name="\App\Livewire\Events\AvailabilityImport::MODAL">
                        <flux:button size="sm" icon="arrow-up-tray" data-test="import-availability-button">{{ __('Import days') }}</flux:button>
                    </flux:modal.trigger>
                    <flux:button
                        size="sm"
                        variant="ghost"
                        wire:click="clearMonth"
                        wire:confirm="{{ __('Clear every free day in :month?', ['month' => $month->format('F')]) }}{{ $pendingFreeDays > 0 ? ' '.trans_choice(':count of them has a booking request waiting for your reply. Clearing the day does not decline it.|:count of them have booking requests waiting for your reply. Clearing a day does not decline them.', $pendingFreeDays) : '' }}"
                    >
                        {{ __('Clear month') }}
                    </flux:button>
                </div>
            </div>
        @endif

        {{--
            Marking a day free (or clearing it) shows straight away: the click flips the day's data-free
            in the browser, then saves. The re-render that follows sets data-free back to what the server
            saved, so a day it refused (e.g. booked in the meantime) flips back by itself.
        --}}
        <div
            class="overflow-hidden rounded-2xl border border-zinc-200 bg-white dark:border-zinc-700 dark:bg-zinc-900"
            x-data="{
                flip(day, date) {
                    // Clearing a day with a request waiting doesn't decline it, so check first.
                    if (day.dataset.free === 'true' && day.dataset.pending && ! confirm(day.dataset.pending)) {
                        return
                    }

                    const free = day.dataset.free !== 'true'
                    day.dataset.free = free
                    day.setAttribute('aria-pressed', free)
                    $wire.toggleDay(date)
                },
            }"
        >
            <div class="grid grid-cols-7 border-b border-zinc-200 bg-brand-tertiary/15 dark:border-zinc-700 dark:bg-zinc-800">
                @foreach (['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'] as $weekday)
                    <div class="px-2 py-2.5 text-center text-xs font-bold tracking-wide text-zinc-500 uppercase dark:text-zinc-400">{{ __($weekday) }}</div>
                @endforeach
            </div>

            @foreach ($this->weeks as $week)
                <div class="grid grid-cols-7">
                    @foreach ($week as $day)
                        @php
                            $key = $day?->format('Y-m-d');
                            $isFree = $day && isset($freeDays[$key]);
                            // A day with an event isn't free, so it can't be marked (only cleared, if it already was).
                            $isToggleable = $day && $markingAvailability && $this->canChange($day) && ($isFree || ! isset($busyDays[$key]));
                        @endphp

                        <div
                            data-free="{{ $isFree ? 'true' : 'false' }}"
                            @if ($isToggleable)
                                role="button"
                                tabindex="0"
                                x-on:click="flip($el, '{{ $key }}')"
                                x-on:keydown.enter.prevent="flip($el, '{{ $key }}')"
                                @if ($pendingByDay->has($key))
                                    data-pending="{{ __('You have a booking request waiting for your reply on :day (:events). Clearing the day does not decline it, so remember to reply on the Bookings page. Clear the day anyway?', ['day' => $day->format('D j M'), 'events' => $pendingByDay->get($key)->map(fn ($booking) => $booking->event->title)->implode(', ')]) }}"
                                @endif
                                aria-pressed="{{ $isFree ? 'true' : 'false' }}"
                                aria-label="{{ $day->format('j F') }}: {{ __('click to mark free or clear') }}"
                                wire:key="day-{{ $key }}"
                                data-test="calendar-day-{{ $key }}"
                            @endif
                            @class([
                                'group min-h-24 border-e border-b border-zinc-200 p-1.5 last:border-e-0 sm:p-2 dark:border-zinc-700',
                                'data-[free=true]:bg-emerald-50 dark:data-[free=true]:bg-emerald-950/40',
                                'cursor-pointer hover:bg-emerald-100/70 dark:hover:bg-emerald-900/40' => $isToggleable,
                                'opacity-50' => $day && $markingAvailability && ! $isToggleable,
                            ])
                        >
                            @if ($day)
                                <div class="flex flex-col gap-1">
                                    <div class="flex items-center justify-between gap-1">
                                        <span @class([
                                            'grid size-6 place-items-center rounded-full text-xs font-semibold',
                                            'bg-brand text-white' => $key === $todayKey,
                                            'text-zinc-900 dark:text-white' => $key !== $todayKey,
                                        ])>{{ $day->day }}</span>

                                        <span class="hidden truncate text-[10px] font-bold tracking-wide text-emerald-700 uppercase sm:group-data-[free=true]:inline dark:text-emerald-300">{{ __('Free') }}</span>
                                    </div>

                                    @foreach ($eventsByDay->get($key, []) as $event)
                                        @php $hosting = $event->user_id === auth()->id(); @endphp
                                        <a
                                            href="{{ $hosting ? route('events.edit', $event) : route('bookings.index') }}"
                                            wire:navigate
                                            title="{{ $hosting ? __('Hosting') : __('Playing') }}: {{ $event->title }} ({{ $event->starts_at->format('H:i') }}){{ $event->status === EventStatus::Draft ? ' - '.__('Draft') : '' }}"
                                            @class([
                                                'truncate rounded-md border px-1.5 py-0.5 text-[11px] font-semibold hover:no-underline',
                                                'border-brand/30 bg-brand/10 text-brand' => $hosting,
                                                'border-violet-300 bg-violet-100 text-violet-800' => ! $hosting,
                                                'border-dashed opacity-70' => $event->status === EventStatus::Draft,
                                                'pointer-events-none' => $markingAvailability,
                                            ])
                                        >
                                            {{ $event->title }}
                                        </a>
                                    @endforeach

                                    @foreach ($pendingByDay->get($key, []) as $booking)
                                        <a
                                            href="{{ route('bookings.index') }}"
                                            wire:navigate
                                            title="{{ __('Request waiting for your reply') }}: {{ $booking->event->title }} ({{ $booking->event->starts_at->format('H:i') }})"
                                            @class([
                                                'truncate rounded-md border border-dashed border-amber-400 bg-amber-50 px-1.5 py-0.5 text-[11px] font-semibold text-amber-800 hover:no-underline',
                                                'pointer-events-none' => $markingAvailability,
                                            ])
                                            data-test="calendar-request-{{ $booking->id }}"
                                        >
                                            {{ $booking->event->title }}?
                                        </a>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                    @endforeach
                </div>
            @endforeach
        </div>
    </div>

    <div class="flex w-full flex-col gap-5 lg:w-80 lg:shrink-0">
        <x-profile-section :title="__('Your Availability')">
            @forelse ($this->upcomingFreeDays as $date)
                <div class="flex items-center gap-2.5 text-zinc-900 dark:text-white">
                    <span class="size-2.5 shrink-0 rounded-full bg-emerald-500"></span>
                    {{ $date->format('D j M') }}
                </div>
            @empty
                <flux:text>{{ __('You have not marked any free days yet. Venues, bands and organisers will be able to find you on the days you are free.') }}</flux:text>
            @endforelse

            @if ($this->availabilityLooksStale())
                <flux:callout icon="clock" color="amber" data-test="stale-availability">
                    <flux:callout.text>{{ __('You have not updated your free days in over :days days. Are they still right? Others see when you last updated them.', ['days' => $this::STALE_AFTER_DAYS]) }}</flux:callout.text>
                </flux:callout>
            @endif

            @if ($updatedAt = auth()->user()->availability_updated_at)
                <flux:text size="sm" data-test="availability-updated-at">{{ __('Last updated :when', ['when' => $updatedAt->diffForHumans()]) }}</flux:text>
            @endif

            @unless ($markingAvailability)
                <div>
                    <flux:link wire:click="$set('markingAvailability', true)" class="cursor-pointer text-sm">{{ __('Mark free days') }}</flux:link>
                </div>
            @endunless
        </x-profile-section>

        <x-profile-section :title="__('Today\'s Events')">
            @forelse ($this->todaysEvents as $event)
                @php $hosting = $event->user_id === auth()->id(); @endphp
                <a href="{{ $hosting ? route('events.edit', $event) : route('bookings.index') }}" wire:navigate class="flex items-start gap-3 hover:no-underline">
                    <span @class(['mt-0.5 grid size-9 shrink-0 place-items-center rounded-xl text-white', 'bg-brand' => $hosting, 'bg-violet-600' => ! $hosting])>
                        <flux:icon.clock variant="mini" />
                    </span>
                    <span class="min-w-0">
                        <span class="block truncate font-bold text-zinc-900 dark:text-white">{{ $event->title }}</span>
                        <span class="block text-xs text-zinc-500 dark:text-zinc-400">{{ $event->starts_at->format('H:i') }} &middot; {{ $event->location }}</span>
                    </span>
                </a>
            @empty
                <flux:text>{{ __('Nothing booked for today.') }}</flux:text>
            @endforelse
        </x-profile-section>

        <x-profile-section :title="__('Key')">
            <div class="flex items-center gap-2.5 text-zinc-900 dark:text-white">
                <span class="size-2.5 shrink-0 rounded-full bg-emerald-500"></span>
                {{ __('Free for a gig') }}
            </div>
            <div class="flex items-center gap-2.5 text-zinc-900 dark:text-white">
                <span class="size-2.5 shrink-0 rounded-full bg-brand"></span>
                {{ __('Hosting: your own event') }}
            </div>
            <div class="flex items-center gap-2.5 text-zinc-900 dark:text-white">
                <span class="size-2.5 shrink-0 rounded-full bg-violet-600"></span>
                {{ __('Playing: you accepted a booking') }}
            </div>
            <div class="flex items-center gap-2.5 text-zinc-900 dark:text-white">
                <span class="size-2.5 shrink-0 rounded-full border border-dashed border-amber-500 bg-amber-100"></span>
                {{ __('Request waiting for your reply') }}
            </div>
            <flux:text size="sm" class="mt-1">{{ __('Faded, dashed entries are drafts only you can see.') }}</flux:text>
        </x-profile-section>
    </div>

    <livewire:events.availability-import />
</div>
