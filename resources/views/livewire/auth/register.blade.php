@php
    use App\Enums\EventDay;
    use App\Enums\LinkKind;
    use App\Enums\SoundEngineer;
    use App\Enums\UserRole;

    $steps = array_values($this->steps());
    $current = $this->currentStep();
    $isLastStep = $this->isLastStep();
@endphp

<div class="rounded-2xl border border-zinc-200 bg-white p-7 shadow-[0_20px_55px_rgba(0,0,0,0.08)]">
    {{-- Header --}}
    <div class="mb-4 flex items-center gap-3">
        <button
            type="button"
            wire:click="back"
            class="grid size-9 place-items-center rounded-lg text-brand-muted transition hover:bg-brand-tertiary/35"
            aria-label="{{ $step === 0 ? __('Back to log in') : __('Previous step') }}"
        >
            <flux:icon.chevron-left class="size-5" />
        </button>
        <h1 class="text-[28px] font-extrabold tracking-[-0.6px] text-brand-ink">{{ __('Create Account') }}</h1>
    </div>

    {{-- Stepper --}}
    <ol class="mb-6 flex" aria-label="{{ __('Sign-up progress') }}">
        @foreach ($steps as $index => $label)
            <li class="relative flex flex-1 flex-col items-center text-center" @if ($index === $step) aria-current="step" @endif>
                @if ($index > 0)
                    <span @class([
                        'absolute top-3 right-[calc(50%+16px)] left-[calc(-50%+16px)] h-px',
                        'bg-brand' => $index <= $step,
                        'bg-zinc-300' => $index > $step,
                    ]) aria-hidden="true"></span>
                @endif

                <span @class([
                    'grid size-6 place-items-center rounded-full text-xs font-semibold text-white',
                    'bg-brand' => $index <= $step,
                    'bg-zinc-400' => $index > $step,
                ])>
                    @if ($index < $step)
                        <flux:icon.check variant="micro" class="size-3.5" />
                    @else
                        {{ $index + 1 }}
                    @endif
                </span>

                <span @class([
                    'mt-2 px-1 text-xs leading-tight',
                    'font-semibold text-brand-ink' => $index === $step,
                    'text-brand-muted' => $index !== $step,
                ])>{{ $label }}</span>
            </li>
        @endforeach
    </ol>

    <form wire:submit="next" wire:key="step-{{ $current }}" class="flex max-h-[60vh] flex-col gap-4 overflow-y-auto px-1 pb-1">
        @switch($current)
            @case('account')
                <flux:text>{{ __('Enter your email and create a password') }}</flux:text>

                <flux:input wire:model="email" :label="__('Email')" type="email" required autofocus autocomplete="email" :placeholder="__('Enter your email')" />

                <flux:input
                    wire:model="password"
                    :label="__('Password')"
                    type="password"
                    required
                    autocomplete="new-password"
                    :placeholder="__('Create a password')"
                    passwordrules="{{ \Illuminate\Validation\Rules\Password::defaults()->toPasswordRulesString() }}"
                    viewable
                />

                <flux:input
                    wire:model="password_confirmation"
                    :label="__('Confirm Password')"
                    type="password"
                    required
                    autocomplete="new-password"
                    :placeholder="__('Confirm your password')"
                    viewable
                />
                @break

            @case('personal')
                <flux:text>{{ __('Tell us a bit about yourself') }}</flux:text>

                <flux:input wire:model="name" :label="__('Your Name')" required autofocus autocomplete="name" :placeholder="__('Enter your name')" />

                <flux:input wire:model.blur="phone" :label="__('Phone Number')" type="tel" required autocomplete="tel" :placeholder="__('Enter your phone number')" />

                <div class="flex flex-col gap-2" role="group" aria-labelledby="role-label">
                    <flux:label id="role-label">{{ __('I am a...') }}</flux:label>
                    @foreach (UserRole::registrable() as $option)
                        <x-choice-card
                            wire:click="$set('role', '{{ $option->value }}')"
                            :label="$option->label()"
                            :description="$option->description()"
                            :selected="$role === $option->value"
                        />
                    @endforeach
                    <flux:error name="role" />
                </div>
                @break

            @case('manager')
                <flux:text>
                    {{ $role === UserRole::Venue->value ? __("Enter the venue manager's contact details") : __("Enter your band manager's contact details") }}
                </flux:text>

                <flux:input wire:model="manager_name" :label="__('Manager Name')" required autofocus :placeholder="__('Enter manager\'s name')" />

                <flux:input wire:model.blur="manager_phone" :label="__('Manager Phone Number')" type="tel" required :placeholder="__('Enter manager\'s phone number')" />

                <flux:input wire:model="manager_email" :label="__('Manager Email')" type="email" required :placeholder="__('Enter manager\'s email')" />
                @break

            @case('venue')
                <flux:text>{{ __('Tell us about your venue') }}</flux:text>

                <flux:input wire:model="capacity" :label="__('Venue Capacity')" type="number" min="1" required autofocus :placeholder="__('Maximum number of people')" />

                <flux:input wire:model="location" :label="__('Venue Location')" required autocomplete="street-address" :placeholder="__('Enter venue address')" />

                <flux:input wire:model="min_door_charge" :label="__('Minimum Door Charge (ZAR)')" type="number" min="0" step="1" required placeholder="0" />

                <div class="flex flex-col gap-2" role="group" aria-labelledby="sound-engineer-label">
                    <flux:label id="sound-engineer-label">{{ __('Sound Engineer') }}</flux:label>
                    @foreach (SoundEngineer::cases() as $option)
                        <x-choice-card
                            wire:click="$set('sound_engineer', '{{ $option->value }}')"
                            :label="$option->label()"
                            :selected="$sound_engineer === $option->value"
                        />
                    @endforeach
                    <flux:error name="sound_engineer" />
                </div>

                @if ($sound_engineer === SoundEngineer::InHouse->value)
                    <flux:input wire:model="sound_engineer_name" :label="__('Sound Engineer Name')" required :placeholder="__('Enter sound engineer\'s name')" />
                @endif

                <div class="flex flex-col gap-2" role="group" aria-labelledby="equipment-label">
                    <flux:label id="equipment-label">{{ __('Do you have your own equipment?') }}</flux:label>
                    <flux:text size="sm" class="-mt-1">{{ __('Drum kit, amps, PA system, etc.') }}</flux:text>
                    <div class="flex gap-2">
                        <x-choice-card wire:click="$set('has_own_equipment', true)" class="text-center" :label="__('Yes')" :selected="$has_own_equipment === true" />
                        <x-choice-card wire:click="$set('has_own_equipment', false)" class="text-center" :label="__('No')" :selected="$has_own_equipment === false" />
                    </div>
                    <flux:error name="has_own_equipment" />
                </div>

                @if ($has_own_equipment)
                    <flux:textarea wire:model="equipment_details" :label="__('Equipment Details (optional)')" rows="3" :placeholder="__('List available equipment...')" />
                @endif
                @break

            @case('event-days')
                <div>
                    <flux:text>{{ __('When do you prefer to host events?') }}</flux:text>
                    <flux:text size="sm">{{ __('You can select both options') }}</flux:text>
                </div>

                <div class="flex flex-col gap-3" role="group" aria-label="{{ __('Event days') }}">
                    @foreach (EventDay::cases() as $option)
                        <x-choice-card
                            wire:click="toggleEventDay('{{ $option->value }}')"
                            checkbox
                            :label="$option->label()"
                            :description="$option->description()"
                            :selected="in_array($option->value, $event_days, true)"
                        />
                    @endforeach
                    <flux:error name="event_days" />
                </div>
                @break

            @case('band')
                <flux:text>{{ __('Tell us about your band') }}</flux:text>

                <flux:input wire:model="band_name" :label="__('Band Name')" required autofocus :placeholder="__('Enter your band\'s name')" />

                <flux:input wire:model="member_count" :label="__('Number of Members')" type="number" min="1" required :placeholder="__('How many members are in the band?')" />

                <flux:textarea wire:model="member_names" :label="__('Member Names')" rows="3" required :placeholder="__('List each member\'s name, separated by commas')" />

                <flux:input wire:model="genre" :label="__('Genre')" required :placeholder="__('e.g. Rock, Jazz, Hip-Hop')" />

                <flux:textarea wire:model="bio" :label="__('Short Bio (optional)')" rows="3" :placeholder="__('Tell us a bit about your band...')" />
                @break

            @case('links')
                <flux:text>{{ __('Share your music and social links') }}</flux:text>

                @include('livewire.auth.partials.link-list', ['field' => 'music_links', 'kind' => LinkKind::Music, 'label' => __('Music Links (optional)'), 'addLabel' => __('Add music link')])

                @include('livewire.auth.partials.link-list', ['field' => 'social_links', 'kind' => LinkKind::Social, 'label' => __('Social Links (optional)'), 'addLabel' => __('Add social link')])
                @break

            @case('organiser')
                <flux:text>{{ __('Tell us about your organisation') }}</flux:text>

                <flux:input wire:model="org_name" :label="__('Organisation Name')" required autofocus :placeholder="__('Enter your organisation\'s name')" />

                <flux:input wire:model.blur="contact_number" :label="__('Contact Number')" type="tel" required autocomplete="tel" :placeholder="__('Enter a contact phone number')" />

                <flux:input wire:model="contact_email" :label="__('Contact Email')" type="email" required autocomplete="email" :placeholder="__('Enter a contact email')" />

                @include('livewire.auth.partials.link-list', ['field' => 'social_links', 'kind' => LinkKind::Social, 'label' => __('Social Links (optional)'), 'addLabel' => __('Add social link')])
                @break
        @endswitch

        <flux:button type="submit" variant="primary" class="mt-2 w-full" data-test="register-next-button">
            {{ $isLastStep ? __('Create Account') : __('Continue') }}
        </flux:button>

        @if ($current === 'account')
            <p class="text-center text-sm text-brand-muted">
                {{ __('Already have an account?') }}
                <flux:link :href="route('login')" wire:navigate class="font-semibold">{{ __('Log in') }}</flux:link>
            </p>
        @endif
    </form>
</div>
