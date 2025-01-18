<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Message extends Model
{
    protected $fillable = [
        'sender_id',
        'receiver_id',
        'message',
        'type',
        'duration',
    ];

    public function sender()
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    public function receiver()
    {
        return $this->belongsTo(User::class, 'receiver_id');
    }

    public function getIsReadAttribute()
    {
        return $this->read_at !== null;
    }

    public function getIsDeliveredAttribute()
    {
        return $this->delivered_at !== null;
    }
}
