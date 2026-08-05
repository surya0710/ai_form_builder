<?php

namespace App\Policies;

use App\Models\Form;
use App\Models\User;

class FormPolicy
{
    public function view(User $user, Form $form): bool
    {
        return $user->is($form->user);
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Form $form): bool
    {
        return $user->is($form->user);
    }

    public function delete(User $user, Form $form): bool
    {
        return $user->is($form->user);
    }
}
