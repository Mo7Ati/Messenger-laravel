<?php

namespace App\Models;

use App\Enums\ChatTypeEnum;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Chat extends Model
{
    protected $fillable = [
        'user_id',
        'last_message_id',
        'label',
        'type',
    ];

    protected $casts = [
        'type' => ChatTypeEnum::class,
    ];

    /*
    | If type is group, then we need to get the participants
    */
    public function participants(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'participants', 'chat_id', 'user_id')
            ->withPivot(['role', 'joined_at']);
    }

    public function messages()
    {
        return $this->hasMany(Message::class);
    }

    /*
    | the creator of the chat
    */
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }

    /*
    | the last message of the chat
    */
    public function lastMessage()
    {
        return $this->belongsTo(Message::class, 'last_message_id', 'id')
            ->withDefault();
    }

    /*
    | the recipients of the chat
    */
    public function recipients()
    {
        return $this->hasManyThrough(
            Recipient::class,
            Message::class,
            'chat_id',
            'message_id',
            'id',
            'id'
        );
    }
}
