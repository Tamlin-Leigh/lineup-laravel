<x-profile-section :title="__('Account Info')">
    @if ($editing)
        @include('partials.profile-fields.personal')

        <div>
            <flux:label>{{ __('Email') }}</flux:label>
            <flux:text class="mt-1">
                {{ $user->email }} &middot;
                <flux:link :href="route('profile.edit')" wire:navigate>{{ __('Change it in settings') }}</flux:link>
            </flux:text>
        </div>
    @else
        <p class="text-zinc-900 dark:text-white">{{ $user->name }}</p>
        <flux:text>{{ $user->phone }}</flux:text>
        <flux:text>{{ $user->email }}</flux:text>
    @endif
</x-profile-section>
