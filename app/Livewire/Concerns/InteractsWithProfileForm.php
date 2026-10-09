<?php

namespace App\Livewire\Concerns;

use App\Livewire\Forms\ProfileForm;

/**
 * Exposes ProfileForm's link helpers as component actions, so views can call them
 * with wire:click. Expects a public ProfileForm $form property. (Event days are
 * toggled in the browser by the choice cards.)
 *
 * @property ProfileForm $form
 */
trait InteractsWithProfileForm
{
    public function addLink(string $field): void
    {
        $this->form->addLink($field);
    }

    public function removeLink(string $field, int $index): void
    {
        $this->form->removeLink($field, $index);
    }
}
