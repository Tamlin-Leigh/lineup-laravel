@php
    $me = auth()->user();
    $active = $this->active;
@endphp

<div class="mx-auto flex w-full max-w-5xl flex-col gap-5">
    <div>
        <flux:heading size="xl" level="1">{{ __('Messages') }}</flux:heading>
        <flux:text class="mt-1">{{ __('A private chat opens for each accepted booking, between the event\'s host and the band or venue.') }}</flux:text>
    </div>

    {{-- On larger screens the chat fills the window below the heading; the thread scrolls inside it. --}}
    <div class="grid min-h-[32rem] grid-cols-1 overflow-hidden rounded-2xl border border-zinc-200 bg-white md:h-[calc(100dvh-12rem)] md:grid-cols-[18rem_1fr]">
        {{-- Chat list (hidden on phones while a chat is open). --}}
        <nav @class(['flex min-h-0 flex-col overflow-y-auto border-zinc-200 md:border-e', 'hidden md:flex' => $active]) aria-label="{{ __('Your chats') }}">
            @forelse ($this->conversations as $conversation)
                @php
                    $other = $conversation->otherParticipant($me);
                    $unread = $conversation->unreadMessagesFor($me)->count();
                    $latest = $conversation->latestMessage;
                @endphp
                <a
                    href="{{ route('messages.show', $conversation) }}"
                    wire:navigate
                    wire:key="conversation-{{ $conversation->id }}"
                    @class([
                        'flex items-center gap-3 border-b border-zinc-100 p-3 hover:bg-zinc-50 hover:no-underline',
                        'bg-brand/5' => $active?->id === $conversation->id,
                    ])
                    data-test="conversation-{{ $conversation->id }}"
                >
                    <x-user-avatar :user="$other" class="size-10 text-xs" />
                    <span class="min-w-0 flex-1">
                        <span class="flex items-center justify-between gap-2">
                            <span @class(['truncate text-zinc-900', 'font-bold' => $unread > 0, 'font-semibold' => $unread === 0])>{{ $other->displayName() }}</span>
                            @if ($unread > 0)
                                <span class="grid min-w-5 place-items-center rounded-full bg-brand px-1.5 text-[11px] font-bold text-white">{{ $unread }}</span>
                            @endif
                        </span>
                        <span class="block truncate text-xs text-zinc-500">{{ $conversation->booking->event->title }}</span>
                        @if ($latest)
                            <span class="block truncate text-xs text-zinc-400">{{ $latest->isSystem() ? '' : ($latest->user_id === $me->id ? __('You').': ' : '') }}{{ $latest->body }}</span>
                        @endif
                    </span>
                </a>
            @empty
                <div class="p-6 text-center text-sm text-zinc-500">
                    {{ __('No chats yet. One opens when a booking is accepted.') }}
                    <flux:link :href="route('bookings.index')" wire:navigate>{{ __('Your bookings') }}</flux:link>
                </div>
            @endforelse
        </nav>

        {{-- The open chat. --}}
        <section @class(['flex min-h-[32rem] flex-col md:min-h-0', 'hidden md:flex' => ! $active])>
            @if ($active)
                @php
                    $other = $active->otherParticipant($me);
                    $event = $active->booking->event;
                    $readOnly = $active->readOnlyReason();
                @endphp

                <header class="flex items-center gap-3 border-b border-zinc-200 p-3">
                    <flux:button size="sm" variant="ghost" icon="chevron-left" class="md:hidden" :href="route('messages.index')" wire:navigate :aria-label="__('All chats')" />
                    <x-user-avatar :user="$other" class="size-10 text-xs" />
                    <div class="min-w-0 flex-1">
                        <a href="{{ route('profiles.show', $other) }}" wire:navigate class="block truncate font-bold text-zinc-900 hover:underline">{{ $other->displayName() }}</a>
                        <span class="block truncate text-xs text-zinc-500">{{ $event->title }} &middot; {{ $event->starts_at->format('D j M Y, H:i') }}</span>
                    </div>
                    @if ($event->user_id === $me->id)
                        <flux:button size="sm" :href="route('events.edit', $event)" wire:navigate>{{ __('Event') }}</flux:button>
                    @endif
                </header>

                <div
                    class="flex max-h-[28rem] min-h-0 flex-1 flex-col gap-2 overflow-y-auto bg-zinc-50 p-4 md:max-h-none"
                    wire:key="thread-{{ $active->id }}-{{ $this->messages->count() }}"
                    x-init="$nextTick(() => $el.scrollTop = $el.scrollHeight)"
                    data-test="thread"
                >
                    @foreach ($this->messages as $message)
                        @if ($message->isSystem())
                            <p class="mx-auto max-w-md rounded-full bg-zinc-200/70 px-3 py-1 text-center text-xs text-zinc-600" wire:key="message-{{ $message->id }}">{{ $message->body }}</p>
                        @else
                            @php $mine = $message->user_id === $me->id; @endphp
                            <div @class(['flex flex-col max-w-[80%]', 'self-end items-end' => $mine, 'self-start items-start' => ! $mine]) wire:key="message-{{ $message->id }}">
                                {{-- Plain text only: Blade escapes it, and line breaks are kept. --}}
                                <p @class([
                                    'whitespace-pre-line break-words rounded-2xl px-3 py-2 text-sm',
                                    'rounded-br-sm bg-brand text-white' => $mine,
                                    'rounded-bl-sm border border-zinc-200 bg-white text-zinc-900' => ! $mine,
                                ])>{{ $message->body }}</p>
                                <span class="mt-0.5 px-1 text-[11px] text-zinc-400">{{ $message->created_at?->format('j M, H:i') }}</span>
                            </div>
                        @endif
                    @endforeach
                </div>

                @if ($readOnly)
                    <p class="border-t border-zinc-200 p-3 text-center text-sm text-zinc-500" data-test="read-only">{{ $readOnly }}</p>
                @else
                    <form wire:submit="send" class="flex items-end gap-2 border-t border-zinc-200 p-3">
                        <div class="flex-1">
                            <flux:textarea
                                wire:model="body"
                                rows="2"
                                resize="none"
                                :placeholder="__('Write a message… (Enter to send, Shift+Enter for a new line)')"
                                :aria-label="__('Message')"
                                maxlength="{{ App\Models\Message::MAX_LENGTH }}"
                                x-on:keydown.enter="if (! $event.shiftKey) { $event.preventDefault(); $wire.send() }"
                            />
                            <flux:error name="body" />
                        </div>
                        <flux:button type="submit" variant="primary" icon="paper-airplane" data-test="send-message">{{ __('Send') }}</flux:button>
                    </form>
                @endif
            @else
                <div class="m-auto p-6 text-center text-sm text-zinc-500">{{ __('Pick a chat to read it.') }}</div>
            @endif
        </section>
    </div>
</div>
