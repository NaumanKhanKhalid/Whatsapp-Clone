<?php

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Broadcast;


Broadcast::channel('message.{id}', function (User $user, $id) {
    return (int) $user->id === (int) $id || (int) $user->id === (int) Auth::id();
});
Broadcast::channel('chat-room', function ($user) {

    $lastMessage = $user->lastMessage();
    return [
        'id' => $user->id,
        'name' => $user->name,
        'avatar_url' => $user->avatar_url,
        'last_message' => $lastMessage ? $lastMessage->message : null,
  'last_message_time' => $lastMessage ? 
    ($lastMessage->created_at->isToday() ? $lastMessage->created_at->format('h:i A') : 
    ($lastMessage->created_at->isYesterday() ? 'Yesterday ' . $lastMessage->created_at->format('h:i A') : 
    $lastMessage->created_at->format('Y-m-d'))) : null,

        'last_seen' => $user->last_seen,
    ];
});
