{{-- The artists playing: the host artist (if an artist is hosting) and the artists that accepted. --}}
@if ($this->lineUp->isNotEmpty())
    <x-profile-section :title="__('Line-up')" data-test="line-up">
        @foreach ($this->lineUp as $performer)
            @php $isHost = $performer->id === ($event?->user_id ?? auth()->id()); @endphp
            <div class="flex items-center gap-3 rounded-xl border border-zinc-200 bg-white p-3" wire:key="line-up-{{ $performer->id }}">
                <x-user-avatar :user="$performer" class="size-10 text-xs" />
                <span class="min-w-0 flex-1">
                    <span class="block truncate font-bold text-zinc-900">{{ $performer->displayName() }}</span>
                    <span class="block truncate text-xs text-zinc-500">{{ $performer->artistProfile?->genre }}</span>
                </span>
                <x-pill>{{ $isHost ? __('Host') : __('Booked') }}</x-pill>
            </div>
        @endforeach
        @if ($this->lineUp->count() === 1 && $this->lineUp->first()->id === auth()->id())
            <flux:text size="sm">{{ __('You are playing as the host. Artists you ask below join the line-up once they accept.') }}</flux:text>
        @endif
    </x-profile-section>
@endif
