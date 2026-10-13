<?php

namespace App\Livewire\Auth;

use App\Actions\RegisterUser;
use App\Concerns\PasswordValidationRules;
use App\Concerns\ProfileValidationRules;
use App\Enums\UserRole;
use App\Livewire\Forms\ProfileForm;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Multi-step sign-up. The account step and the role choice live here; every other
 * field is in ProfileForm, whose sections become the remaining steps for the role.
 * Each step is validated before moving on, and every step is validated again at the
 * end, so skipping ahead never bypasses a rule.
 */
#[Layout('layouts::auth.brand')]
#[Title('Create account')]
class Register extends Component
{
    use PasswordValidationRules, ProfileValidationRules;

    private const STEP_LABELS = [
        'account' => 'Account Details',
        'personal' => 'Personal Info',
        'manager' => 'Manager Details',
        'venue' => 'Venue Details',
        'event-days' => 'Event Preferences',
        'band' => 'Band Details',
        'links' => 'Links',
        'organiser' => 'Organiser Details',
    ];

    #[Locked]
    public int $step = 0;

    public string $email = '';

    public string $password = '';

    public string $password_confirmation = '';

    public string $role = '';

    public ProfileForm $form;

    /**
     * Step key => label for the chosen role.
     *
     * @return array<string, string>
     */
    public function steps(): array
    {
        $keys = ['account', ...ProfileForm::sectionsFor(UserRole::tryFrom($this->role))];

        return array_combine($keys, array_map(fn (string $key): string => __(self::STEP_LABELS[$key]), $keys));
    }

    public function currentStep(): string
    {
        return array_keys($this->steps())[$this->step] ?? 'account';
    }

    public function isLastStep(): bool
    {
        return $this->role !== '' && $this->step === count($this->steps()) - 1;
    }

    /**
     * Validate the current step, then advance (or create the account on the last step).
     */
    public function next(RegisterUser $registerUser): void
    {
        $current = $this->currentStep();

        if ($current === 'account') {
            $this->validateAccount();
        } elseif ($current === 'personal') {
            $this->validateTogether(
                fn () => $this->validateRole(),
                fn () => $this->form->validateSections(['personal']),
            );
        } else {
            $this->form->validateSections([$current]);
        }

        if (! $this->isLastStep()) {
            $this->step++;

            return;
        }

        $account = $this->validateAccount();
        $role = $this->validateRole();
        $profile = $this->form->validateSections(ProfileForm::sectionsFor(UserRole::from($role['role'])));

        $user = $registerUser->handle([...$profile, ...$account, ...$role]);

        Auth::login($user);
        Session::regenerate();

        $this->redirect(route('dashboard', absolute: false), navigate: true);
    }

    public function back(): void
    {
        $this->resetErrorBag();

        if ($this->step === 0) {
            $this->redirectRoute('login', navigate: true);

            return;
        }

        $this->step--;
    }

    /**
     * Run several validators and report all of their errors at once.
     */
    private function validateTogether(callable ...$validators): void
    {
        $errors = [];

        foreach ($validators as $validate) {
            try {
                $validate();
            } catch (ValidationException $e) {
                $errors = [...$errors, ...$e->errors()];
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function validateAccount(): array
    {
        $this->email = Str::lower(trim($this->email));

        return $this->validate([
            'email' => $this->emailRules(),
            'password' => $this->passwordRules(),
        ]);
    }

    /**
     * @return array{role: string}
     */
    private function validateRole(): array
    {
        /** @var array{role: string} */
        return $this->validate(
            ['role' => ['required', Rule::enum(UserRole::class)->only(UserRole::registrable())]],
            ['role.required' => __('Choose the type of account you need.')],
            ['role' => __('account type')],
        );
    }
}
