<flux:input wire:model="form.band_name" :label="__('Band Name')" required :placeholder="__('Enter your band\'s name')" />

<flux:input wire:model="form.genre" :label="__('Genre')" required :placeholder="__('e.g. Rock, Jazz, Hip-Hop')" />

<flux:textarea wire:model="form.bio" :label="__('Short Bio (optional)')" rows="3" :placeholder="__('Tell us a bit about your band...')" />
