<?php

namespace App\Models;

use App\Enums\ConversationTypeEnum;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class Conversation extends Model
{
    protected $fillable = [
        'user_id',
        'last_message_id',
        'label',
        'type',
    ];

    protected $casts = [
        'type' => ConversationTypeEnum::class,
    ];

    /*
    | If type is group, then we need to get the participants
    */
    public function participants()
    {
        return $this->belongsToMany(User::class, 'participants', 'conversation_id', 'user_id')
            ->where('participants.user_id', '<>', Auth::id())
            ->withPivot(['role', 'joined_at']);
    }

    public function messages()
    {
        return $this->hasMany(Message::class);
    }

    /*
    | the creator of the conversation
    */
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }

    /*
    | the last message of the conversation
    */
    public function lastMessage()
    {
        return $this->belongsTo(Message::class, 'last_message_id', 'id')
            ->withDefault();
    }

    /*
    | the recipients of the conversation
    */
    public function recipients()
    {
        return $this->hasManyThrough(
            Recipient::class,
            Message::class,
            'conversation_id',
            'message_id',
            'id',
            'id'
        );
    }

}
