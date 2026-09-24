<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Shared policy for inventory records: everyone signed in can read, admins and engineers can write.
 */
class InventoryPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Model $model): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return $this->canWrite($user);
    }

    public function update(User $user, Model $model): bool
    {
        return $this->canWrite($user);
    }

    public function delete(User $user, Model $model): bool
    {
        return $this->canWrite($user);
    }

    private function canWrite(User $user): bool
    {
        return in_array($user->role, [UserRole::Admin, UserRole::Engineer], true);
    }
}
