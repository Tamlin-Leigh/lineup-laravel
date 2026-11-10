<flux:select wire:model="form.region" :label="__('Region')" :placeholder="__('Choose your region')" required data-test="profile-region">
    @foreach (App\Enums\Region::options() as $value => $label)
        <flux:select.option value="{{ $value }}">{{ __($label) }}</flux:select.option>
    @endforeach
</flux:select>
