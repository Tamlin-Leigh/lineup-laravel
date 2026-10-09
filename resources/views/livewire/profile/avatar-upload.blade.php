<div class="flex flex-col items-center gap-1">
    <label
        class="group relative grid size-16 shrink-0 cursor-pointer place-items-center overflow-hidden rounded-full bg-brand text-white focus-within:ring-2 focus-within:ring-brand-secondary focus-within:ring-offset-2"
        title="{{ __('Change profile photo') }}"
    >
        <input type="file" wire:model="photo" accept="image/png,image/jpeg,image/webp" class="sr-only" aria-label="{{ __('Change profile photo') }}" />

        @if ($photoUrl = $user->profilePhotoUrl())
            <img src="{{ $photoUrl }}" alt="" class="size-full object-cover" />
        @else
            <span class="text-lg font-bold">{{ $user->initials() }}</span>
        @endif

        <span class="absolute inset-0 grid place-items-center bg-black/40 opacity-0 transition group-hover:opacity-100" wire:loading.class="opacity-100" wire:target="photo">
            <flux:icon.camera class="size-5" wire:loading.remove wire:target="photo" />
            <flux:icon.loading class="size-5" wire:loading wire:target="photo" />
        </span>
    </label>

    @if ($user->profile_photo_path)
        <button type="button" wire:click="removePhoto" class="text-xs text-brand-muted hover:underline dark:text-zinc-400">
            {{ __('Remove') }}
        </button>
    @endif

    <flux:error name="photo" class="max-w-40 text-center text-xs" />
</div>
