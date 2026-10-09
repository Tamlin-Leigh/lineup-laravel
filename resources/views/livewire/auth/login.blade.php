<x-layouts::auth :title="__('Log in')">
    <div class="flex flex-col gap-5">
        <x-auth-header :title="__('Welcome Back')" :description="__('Sign in to your account')" />

        <!-- Session Status -->
        <x-auth-session-status class="text-center" :status="session('status')" />

        <form method="POST" action="{{ route('login.store') }}" class="flex flex-col gap-4">
            @csrf

            <!-- Email Address -->
            <flux:input
                name="email"
                :label="__('Email')"
                :value="old('email')"
                type="email"
                required
                autofocus
                autocomplete="email"
                :placeholder="__('Enter your email')"
            />

            <!-- Password -->
            <div class="relative">
                <flux:input
                    name="password"
                    :label="__('Password')"
                    type="password"
                    required
                    autocomplete="current-password"
                    :placeholder="__('Enter your password')"
                    viewable
                />

                @if (Route::has('password.request'))
                    <flux:link class="absolute top-0 text-sm end-0" :href="route('password.request')" wire:navigate>
                        {{ __('Forgot your password?') }}
                    </flux:link>
                @endif
            </div>

            <!-- Remember Me -->
            <flux:checkbox name="remember" :label="__('Remember me')" :checked="old('remember')" />

            <x-auth-button class="mt-1" data-test="login-button">
                {{ __('Log in') }}
            </x-auth-button>
        </form>

        <p class="text-center text-sm text-brand-muted">
            {{ __('Don\'t have an account?') }}
            <flux:link :href="route('register')" wire:navigate class="font-semibold">{{ __('Sign up') }}</flux:link>
        </p>
    </div>
</x-layouts::auth>
