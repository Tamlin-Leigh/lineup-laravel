{{-- Artists and venues: a nudge when booking requests are waiting for a reply. --}}
@if ($pendingBookings > 0)
    <a href="{{ route('bookings.index') }}" wire:navigate class="flex items-center gap-3 rounded-2xl border border-amber-200 bg-amber-50 p-4 text-amber-900 hover:no-underline" data-test="pending-bookings">
        <flux:icon.inbox class="shrink-0" />
        <span class="flex-1 font-semibold">
            {{ trans_choice('You have :count booking request waiting for your reply.|You have :count booking requests waiting for your reply.', $pendingBookings) }}
        </span>
        <span class="text-sm font-bold">{{ __('Reply') }} &rarr;</span>
    </a>
@endif
