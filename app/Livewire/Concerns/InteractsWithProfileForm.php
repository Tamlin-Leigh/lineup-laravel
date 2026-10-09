<?php

namespace App\Livewire\Concerns;

use App\Livewire\Forms\ProfileForm;

/**
 * Exposes ProfileForm's list helpers as component actions, so views can call them
 * with wire:click. Expects a public ProfileForm $form property.
 *
 * @property ProfileForm $form
 */
trait InteractsWithProfileForm
{
    public function toggleEventDay(string $day): void
    {
        $this->form->toggleEventDay($day);
    }

    public function addLink(string $field): void
    {
        $this->form->addLink($field);
    }

    public function removeLink(string $field, int $index): void
    {
        $this->form->removeLink($field, $index);
    }
}
