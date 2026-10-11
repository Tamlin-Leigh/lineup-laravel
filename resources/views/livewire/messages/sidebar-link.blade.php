<div>
    <flux:sidebar.item
        icon="chat-bubble-left-right"
        :href="route('messages.index')"
        :current="request()->routeIs('messages.*')"
        :badge="$unread ?: null"
        wire:navigate
        data-test="sidebar-messages"
    >
        {{ __('Messages') }}
    </flux:sidebar.item>
</div>
