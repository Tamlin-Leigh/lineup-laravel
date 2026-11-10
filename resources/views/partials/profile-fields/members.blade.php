<flux:input wire:model="form.member_count" :label="__('Number of Members')" type="number" min="1" required :placeholder="__('How many members perform as this artist?')" />

<flux:textarea wire:model="form.member_names" :label="__('Member Names')" rows="3" required :placeholder="__('List each member\'s name, separated by commas')" />
