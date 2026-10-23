<?php

namespace App\Policies;

use App\Models\Template;
use App\Models\User;

class TemplatePolicy
{
    public function viewAny(User $user): bool
    {
        return true; // both roles can list templates
    }

    public function view(User $user, Template $template): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return true; // both admin and manager can create templates
    }

    public function update(User $user, Template $template): bool
    {
        return $user->isAdmin() || $template->created_by === $user->id;
    }

    public function delete(User $user, Template $template): bool
    {
        return $user->isAdmin() || $template->created_by === $user->id;
    }
}
