<?php

use Illuminate\Support\Facades\Broadcast;

// Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
//     return (int) $user->id === (int) $id;
// });

Broadcast::channel('messenger.{id}', function ($user, $id) {
    return $user->conversations()->where('id', $id)->exists();
});

