<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Database\Eloquent\SoftDeletes;

class Recipient extends Pivot
{
    use SoftDeletes;

    protected $table = "recipients";
    public $timestamps = false;

    protected $casts = [
        'read_at' => 'datetime',
    ];
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
    public function message()
    {
        return $this->belongsTo(Message::class, 'message_id');
    }

}
