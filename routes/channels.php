<?php

use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('messenger.user.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

Broadcast::channel('messenger', function ($user) {
    return $user;
});
