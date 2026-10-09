@php
    use App\Enums\EventCategory;
    use App\Enums\EventStatus;

    $month = $this->visibleMonth();
    $todayKey = now()->format('Y-m-d');
    $eventsByDay = $this->eventsByDay;
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

            <flux:button
                variant="primary"
                icon="plus"
                :href="route('events.create', ['date' => $month->isSameMonth(now()) ? $todayKey : $month->format('Y-m-d')])"
                wire:navigate
            >
                {{ __('Add Event') }}
            </flux:button>
        </div>

        <div class="overflow-hidden rounded-2xl border border-zinc-200 bg-white dark:border-zinc-700 dark:bg-zinc-900">
            <div class="grid grid-cols-7 border-b border-zinc-200 bg-brand-tertiary/15 dark:border-zinc-700 dark:bg-zinc-800">
                @foreach (['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'] as $weekday)
                    <div class="px-2 py-2.5 text-center text-xs font-bold tracking-wide text-zinc-500 uppercase dark:text-zinc-400">{{ __($weekday) }}</div>
                @endforeach
            </div>

            @foreach ($this->weeks as $week)
                <div class="grid grid-cols-7">
                    @foreach ($week as $day)
                        <div class="min-h-24 border-e border-b border-zinc-200 p-1.5 last:border-e-0 sm:p-2 dark:border-zinc-700">
                            @if ($day)
                                @php $key = $day->format('Y-m-d'); @endphp

                                <div class="flex flex-col gap-1">
                                    <span @class([
                                        'grid size-6 place-items-center rounded-full text-xs font-semibold',
                                        'bg-brand text-white' => $key === $todayKey,
                                        'text-zinc-900 dark:text-white' => $key !== $todayKey,
                                    ])>{{ $day->day }}</span>

                                    @foreach ($eventsByDay->get($key, []) as $event)
                                        @php [$from] = $event->category->accent(); @endphp
                                        <a
                                            href="{{ route('events.edit', $event) }}"
                                            wire:navigate
                                            title="{{ $event->title }} ({{ $event->starts_at->format('H:i') }}){{ $event->status === EventStatus::Draft ? ' - '.__('Draft') : '' }}"
                                            @class([
                                                'truncate rounded-md px-1.5 py-0.5 text-[11px] font-semibold hover:no-underline',
                                                'border border-dashed opacity-70' => $event->status === EventStatus::Draft,
                                            ])
                                            style="background-color: color-mix(in srgb, {{ $from }} 15%, transparent); color: {{ $from }}; border-color: {{ $from }}"
                                        >
                                            {{ $event->title }}
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
        <x-profile-section :title="__('Today\'s Events')">
            @forelse ($this->todaysEvents as $event)
                @php [$from] = $event->category->accent(); @endphp
                <a href="{{ route('events.edit', $event) }}" wire:navigate class="flex items-start gap-3 hover:no-underline">
                    <span class="mt-0.5 grid size-9 shrink-0 place-items-center rounded-xl text-white" style="background-color: {{ $from }}">
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

        <x-profile-section :title="__('Event Types')">
            @foreach (EventCategory::cases() as $category)
                <div class="flex items-center gap-2.5 text-zinc-900 dark:text-white">
                    <span class="size-2.5 shrink-0 rounded-full" style="background-color: {{ $category->accent()[0] }}"></span>
                    {{ $category->label() }}
                </div>
            @endforeach
            <flux:text size="sm" class="mt-1">{{ __('Dashed entries are drafts.') }}</flux:text>
        </x-profile-section>
    </div>
</div>
