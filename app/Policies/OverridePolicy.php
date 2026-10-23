<?php

namespace App\Policies;

use App\Models\CriterionResult;
use App\Models\User;

class OverridePolicy
{
    public function create(User $user, CriterionResult $result): bool
    {
        // Both roles can override, but only on calls they can view
        return $user->isAdmin() || $result->call->uploaded_by === $user->id;
    }
}
