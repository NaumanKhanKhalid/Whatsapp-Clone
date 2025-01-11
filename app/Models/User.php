<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Models\Message;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Facades\Auth;

class User extends Authenticatable
{
    public function lastMessage()
    {
        return $this->hasOne(Message::class, 'sender_id')
            ->orWhere('receiver_id', $this->id)
            ->latest()
            ->first();
    }
  
}
