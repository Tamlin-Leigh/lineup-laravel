@php use App\Enums\UserRole; @endphp

<x-layouts::app :title="__('Admin')">
    <div class="mx-auto flex w-full max-w-3xl flex-col gap-5">
        <div>
            <flux:heading size="xl" level="1">{{ __('Admin') }}</flux:heading>
            <flux:text class="mt-1">{{ __('Accounts on the platform') }}</flux:text>
        </div>

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
            <x-stat-card :label="__('Venues')" :value="$roleCounts[UserRole::Venue->value]" icon="building-storefront" />
            <x-stat-card :label="__('Bands')" :value="$roleCounts[UserRole::Band->value]" icon="musical-note" />
            <x-stat-card :label="__('Organisers')" :value="$roleCounts[UserRole::Organiser->value]" icon="megaphone" />
        </div>

        <x-profile-section :title="__('Recent Sign-ups')">
            @if ($recentUsers->isEmpty())
                <flux:text>{{ __('Nobody has signed up yet.') }}</flux:text>
            @else
                <flux:table>
                    <flux:table.columns>
                        <flux:table.column>{{ __('Name') }}</flux:table.column>
                        <flux:table.column>{{ __('Role') }}</flux:table.column>
                        <flux:table.column>{{ __('Joined') }}</flux:table.column>
                    </flux:table.columns>
                    <flux:table.rows>
                        @foreach ($recentUsers as $recent)
                            <flux:table.row :key="$recent->id">
                                <flux:table.cell>
                                    <span class="font-medium text-zinc-900 dark:text-white">{{ $recent->name }}</span>
                                    <span class="block text-xs text-zinc-500 dark:text-zinc-400">{{ $recent->email }}</span>
                                </flux:table.cell>
                                <flux:table.cell><x-pill>{{ $recent->role->label() }}</x-pill></flux:table.cell>
                                <flux:table.cell>{{ $recent->created_at?->diffForHumans() }}</flux:table.cell>
                            </flux:table.row>
                        @endforeach
                    </flux:table.rows>
                </flux:table>
            @endif
        </x-profile-section>
    </div>
</x-layouts::app>
