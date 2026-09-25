<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/** Channel targets are contact details: admins and engineers can see them, only admins change them (or send a test). */
class AlertChannelPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->role !== UserRole::Viewer;
    }

    public function view(User $user, Model $model): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->role === UserRole::Admin;
    }

    public function update(User $user, Model $model): bool
    {
        return $this->create($user);
    }

    public function delete(User $user, Model $model): bool
    {
        return $this->create($user);
    }
}
