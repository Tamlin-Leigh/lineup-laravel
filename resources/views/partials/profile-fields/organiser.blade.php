<flux:input wire:model="form.org_name" :label="__('Organisation Name')" required :placeholder="__('Enter your organisation\'s name')" />

<x-phone-input wire:model="form.contact_number" :label="__('Contact Number')" required :placeholder="__('Enter a contact phone number')" />

<flux:input wire:model="form.contact_email" :label="__('Contact Email')" type="email" required autocomplete="email" :placeholder="__('Enter a contact email')" />
