<flux:input wire:model="form.manager_name" :label="__('Manager Name')" required :placeholder="__('Enter manager\'s name')" />

<x-phone-input wire:model="form.manager_phone" :label="__('Manager Phone Number')" required :placeholder="__('Enter manager\'s phone number')" />

<flux:input wire:model="form.manager_email" :label="__('Manager Email')" type="email" required :placeholder="__('Enter manager\'s email')" />
