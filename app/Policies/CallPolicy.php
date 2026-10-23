<?php

namespace App\Policies;

use App\Models\Call;
use App\Models\User;

class CallPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Call $call): bool
    {
        return $user->isAdmin() || $call->uploaded_by === $user->id;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function delete(User $user, Call $call): bool
    {
        return $user->isAdmin() || $call->uploaded_by === $user->id;
    }
}
