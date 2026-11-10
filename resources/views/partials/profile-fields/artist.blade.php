<flux:input wire:model="form.artist_name" :label="__('Artist Name')" required :placeholder="__('Enter your artist\'s name')" />

<flux:input wire:model="form.genre" :label="__('Genre')" required :placeholder="__('e.g. Rock, Jazz, Hip-Hop')" />

<flux:textarea wire:model="form.bio" :label="__('Short Bio (optional)')" rows="3" :placeholder="__('Tell us a bit about the artist...')" />

{{-- How the artist usually gets paid, shown to hosts picking artists. Both optional. --}}
<flux:input
    wire:model="form.booking_fee"
    type="number"
    min="0"
    step="1"
    :label="__('Booking Fee (ZAR, optional)')"
    :description="__('Your usual fee for a set. Leave empty if it depends on the gig.')"
    :placeholder="__('e.g. 2500')"
/>

<flux:checkbox wire:model="form.accepts_door_split" :label="__('Open to door / ticket splits')" :description="__('Happy to play for a share of the door or ticket sales instead of, or as well as, a fee.')" />
