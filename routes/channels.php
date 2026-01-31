<?php

use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('voice.{userId}', function ($user, $userId) {
    return (int) $user->id === (int) $userId;
});