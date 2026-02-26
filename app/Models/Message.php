<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Message extends Model
{
    use SoftDeletes;
    protected $fillable = [
        'user_id',
        'conversation_id',
        'body',
        'type',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'id')
            ->withDefault([
                'name' => 'User',
            ]);
    }

    public function conversation()
    {
        return $this->belongsTo(Conversation::class, 'conversation_id', 'id');
    }

    public function recipients()
    {
        return $this->belongsToMany(User::class, 'recipients', 'message_id', 'user_id')
            ->withPivot(['read_at', 'deleted_at']);
    }

}
