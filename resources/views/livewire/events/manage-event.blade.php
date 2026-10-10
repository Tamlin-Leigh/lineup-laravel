@php
    $bookingDate = Carbon\Carbon::canBeCreatedFromFormat($form->date, 'Y-m-d') ? Carbon\Carbon::createFromFormat('Y-m-d', $form->date) : null;
    $freeOn = $bookingDate ? __('free on :date', ['date' => $bookingDate->format('l j F Y')]) : null;
@endphp

<div class="mx-auto flex w-full max-w-2xl flex-col gap-5">
    <div>
        <flux:text size="sm">
            <flux:link :href="route('events.index')" wire:navigate>{{ __('Events') }}</flux:link> / {{ $event ? __('Edit') : __('New') }}
        </flux:text>
        <flux:heading size="xl" level="1">{{ $event ? $event->title : __('New Event') }}</flux:heading>
    </div>

    @if ($event === null)
        {{-- New event: a step at a time, in the order the choices depend on each other. --}}
        @php
            $steps = array_values($this->steps());
            $current = $this->currentStep();
        @endphp

        <ol class="flex" aria-label="{{ __('New event progress') }}">
            @foreach ($steps as $index => $label)
                <li class="relative flex flex-1 flex-col items-center text-center" @if ($index === $step) aria-current="step" @endif>
                    @if ($index > 0)
                        <span @class([
                            'absolute top-3 right-[calc(50%+16px)] left-[calc(-50%+16px)] h-px',
                            'bg-brand' => $index <= $step,
                            'bg-zinc-300' => $index > $step,
                        ]) aria-hidden="true"></span>
                    @endif

                    <span @class([
                        'grid size-6 place-items-center rounded-full text-xs font-semibold text-white',
                        'bg-brand' => $index <= $step,
                        'bg-zinc-400' => $index > $step,
                    ])>
                        @if ($index < $step)
                            <flux:icon.check variant="micro" class="size-3.5" />
                        @else
                            {{ $index + 1 }}
                        @endif
                    </span>

                    <span @class([
                        'mt-2 px-1 text-xs leading-tight',
                        'font-semibold text-zinc-900' => $index === $step,
                        'text-zinc-500' => $index !== $step,
                    ])>{{ $label }}</span>
                </li>
            @endforeach
        </ol>

        <form wire:submit="next" wire:key="step-{{ $current }}" class="flex flex-col gap-5" data-test="event-step-{{ $current }}">
            @switch($current)
                @case('when')
                    <x-profile-section :title="__('When is it?')">
                        @if ($this->canSendBookingRequests())
                            <flux:text>{{ __('Start with the date: the next steps show who is free that day.') }}</flux:text>
                        @endif
                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <flux:input wire:model="form.date" :label="__('Date')" type="date" required />
                            <flux:input wire:model="form.time" :label="__('Start Time')" type="time" required />
                        </div>
                        @unless ($this->canBookVenue())
                            <flux:input wire:model="form.location" :label="__('Location')" required :placeholder="__('Venue name and city')" />
                        @endunless
                    </x-profile-section>
                    @break

                @case('where')
                    @include('partials.event-form.venue-picker', ['withElsewhere' => true])
                    @break

                @case('line-up')
                    @include('partials.event-form.line-up')
                    @break

                @default
                    @include('partials.event-form.details')

                    @php $picked = $this->pickedAccounts(); @endphp
                    @if ($picked->isNotEmpty())
                        <x-profile-section :title="__('Booking Requests')" data-test="request-summary">
                            <flux:text>
                                {{ trans_choice('When you create the event, :count request goes out by email and to their Bookings page, with :days days to accept or decline:|When you create the event, :count requests go out by email and to their Bookings page, with :days days to accept or decline:', $picked->count(), ['days' => App\Models\BookingRequest::REPLY_DAYS]) }}
                            </flux:text>
                            <div class="flex flex-wrap gap-2">
                                @foreach ($picked as $account)
                                    <x-pill class="px-3 py-1">{{ $account->displayName() }} &middot; {{ $account->role->label() }}</x-pill>
                                @endforeach
                            </div>
                            <flux:textarea wire:model="bookingMessage" :label="__('Message (optional)')" rows="2" :placeholder="__('e.g. Looking for a 45 minute set; we provide the PA.')" />
                        </x-profile-section>
                    @endif

                    <flux:error name="booking" />
            @endswitch

            <div class="flex justify-between gap-2">
                <flux:button wire:click="back" data-test="event-back-button">{{ $step === 0 ? __('Cancel') : __('Back') }}</flux:button>
                <flux:button type="submit" variant="primary" data-test="{{ $this->isLastStep() ? 'save-event-button' : 'event-next-button' }}">
                    {{ $this->isLastStep() ? __('Create Event') : __('Continue') }}
                </flux:button>
            </div>
        </form>
    @else
        {{-- Editing: everything on one page. --}}
        <form wire:submit="save" class="flex flex-col gap-5">
            @include('partials.event-form.details')

            <x-profile-section :title="__('When & Where')">
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <flux:input wire:model.live="form.date" :label="__('Date')" type="date" required />
                    <flux:input wire:model="form.time" :label="__('Start Time')" type="time" required />
                </div>

                @if ($event->venue && $event->venue->user_id !== $event->user_id)
                    {{-- A venue that accepted a booking request sets the location. --}}
                    <flux:text>{{ __('At :venue, who accepted your booking request.', ['venue' => $event->location]) }}</flux:text>
                @else
                    <flux:input wire:model="form.location" :label="__('Location')" required :placeholder="__('Venue name and city')" />
                @endif
            </x-profile-section>

            @if ($this->canSendBookingRequests())
                @if ($this->bookingRequests->isNotEmpty())
                    <x-profile-section :title="__('Already Asked')">
                        @foreach ($this->bookingRequests as $booking)
                            <x-booking-row :booking="$booking" for="sent" :show-event="false" wire:key="booking-{{ $booking->id }}" />
                        @endforeach
                    </x-profile-section>
                @endif

                @if ($this->canBookVenue())
                    @include('partials.event-form.venue-picker')
                @endif

                @include('partials.event-form.line-up')
                @include('partials.event-form.request-message')

                <flux:error name="booking" />
            @else
                {{-- Past or cancelled: no more requests, but still show who played. --}}
                @include('partials.event-form.line-up-list')
            @endif

            <div class="flex justify-end gap-2">
                <flux:button :href="route('events.index')" wire:navigate>{{ __('Cancel') }}</flux:button>
                <flux:button type="submit" variant="primary" data-test="save-event-button">{{ __('Save Changes') }}</flux:button>
            </div>
        </form>
    @endif
</div>
