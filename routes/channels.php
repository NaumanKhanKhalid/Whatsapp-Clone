<?php

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Broadcast;


Broadcast::channel('message.{id}', function (User $user, $id) {
    return (int) $user->id === (int) $id || (int) $user->id === (int) Auth::id();
});
Broadcast::channel('chat-room', function ($user) {

    return [
        'id' => $user->id,
        'name' => $user->name,
        'avatar_url' => $user->avatar_url,
        'last_message' => $user->lastMessage ? $user->lastMessage->message : null,
    ];
});
