<?php

use Illuminate\Support\Facades\Broadcast;

// The live status board: any signed-in user may listen (same read access as the monitor list).
Broadcast::channel('monitors', fn ($user) => true);

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});
