@php
    use App\Enums\UserRole;

    $steps = array_values($this->steps());
    $current = $this->currentStep();
    $isLastStep = $this->isLastStep();
@endphp

<div>
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

    <form wire:submit="next" wire:key="step-{{ $current }}" class="flex max-h-[60vh] flex-col gap-4 overflow-y-auto px-1 pb-1 *:shrink-0">
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

                @include('partials.profile-fields.personal')

                <div class="flex flex-col gap-2" role="group" aria-labelledby="role-label">
                    <flux:label id="role-label">{{ __('I am a...') }}</flux:label>
                    @foreach (UserRole::registrable() as $option)
                        <x-choice-card model="role" :value="$option->value" :label="$option->label()" :description="$option->description()" />
                    @endforeach
                    <flux:error name="role" />
                </div>
                @break

            @case('manager')
                <flux:text>
                    {{ $role === UserRole::Venue->value ? __("Enter the venue manager's contact details") : __("Enter your band manager's contact details") }}
                </flux:text>

                @include('partials.profile-fields.manager')
                @break

            @case('venue')
                <flux:text>{{ __('Tell us about your venue') }}</flux:text>

                @include('partials.profile-fields.venue')
                @include('partials.profile-fields.links', ['socialOnly' => true])
                @break

            @case('event-days')
                <div>
                    <flux:text>{{ __('When do you prefer to host events?') }}</flux:text>
                    <flux:text size="sm">{{ __('You can select both options') }}</flux:text>
                </div>

                @include('partials.profile-fields.event-days')
                @break

            @case('band')
                <flux:text>{{ __('Tell us about your band') }}</flux:text>

                @include('partials.profile-fields.band')
                @include('partials.profile-fields.members')
                @break

            @case('links')
                <flux:text>{{ __('Share your music and social links') }}</flux:text>

                @include('partials.profile-fields.links')
                @break

            @case('organiser')
                <flux:text>{{ __('Tell us about your organisation') }}</flux:text>

                @include('partials.profile-fields.organiser')
                @include('partials.profile-fields.links', ['socialOnly' => true])
                @break
        @endswitch

        <x-auth-button class="mt-2" data-test="register-next-button">
            {{ $isLastStep ? __('Create Account') : __('Continue') }}
        </x-auth-button>

        @if ($current === 'account')
            <p class="text-center text-sm text-brand-muted">
                {{ __('Already have an account?') }}
                <flux:link :href="route('login')" wire:navigate class="font-semibold">{{ __('Log in') }}</flux:link>
            </p>
        @endif
    </form>
</div>
