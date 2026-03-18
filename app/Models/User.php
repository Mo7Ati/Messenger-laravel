<?php

namespace App\Models;

use App\Enums\ContactStatusEnum;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'username',
        'name',
        'email',
        'password',
        'last_active_at',
        'phone',
        'is_discoverable',
        'bio',
        'avatar',
    ];

    protected $appends = [
        'avatar_url',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_discoverable' => 'boolean',
        ];
    }

    public function chat()
    {
        return $this->hasOne(Chat::class);
    }

    /*
     * returns all chats for the user
     */
    public function chats()
    {
        return $this->belongsToMany(Chat::class, 'participants')
            ->latest('last_message_id')
            ->withPivot(['role', 'joined_at']);
    }

    /*
     * returns all messages sent by the user
     */
    public function sentMessages()
    {
        return $this->hasMany(Message::class);
    }

    /*
     * returns all messages received by the user
     */
    public function receivedMessages()
    {
        return $this->belongsToMany(Message::class, 'recipients')
            ->withPivot(['read_at', 'deleted_at']);
    }

    public function contactsSent()
    {
        return $this->belongsToMany(
            User::class,
            'contacts',
            'sender_id',
            'receiver_id'
        )->withPivot(['status', 'accepted_at']);
    }

    public function contactsReceived()
    {
        return $this->belongsToMany(
            User::class,
            'contacts',
            'receiver_id',
            'sender_id'
        )->withPivot(['status', 'accepted_at']);
    }

    public function contacts(): Builder
    {
        $id = $this->id;

        $contactUserIds = Contact::query()
            ->where('status', ContactStatusEnum::ACCEPTED)
            ->where(function ($q) use ($id) {
                $q->where('sender_id', $id)
                    ->orWhere('receiver_id', $id);
            })
            ->selectRaw(
                'CASE
                    WHEN sender_id = ? THEN receiver_id
                    ELSE sender_id
                 END',
                [$id]
            );

        return User::query()->whereIn('id', $contactUserIds);
    }

    public function isContactWith($userId): bool
    {
        return $this->contacts()->where('users.id', $userId)->exists();
    }

    public function hasSentRequestTo($userId): bool
    {
        return $this->contactsSent()->where([
            'contacts.receiver_id' => $userId,
            'contacts.status' => ContactStatusEnum::PENDING,
        ])->exists();
    }

    public function hasPendingRequestFrom($userId): bool
    {
        return $this->contactsReceived()->where([
            'contacts.sender_id' => $userId,
            'contacts.status' => ContactStatusEnum::PENDING,
        ])->exists();
    }

    public function contactStatus($userId): string
    {
        if ($this->isContactWith($userId)) {
            return 'contacts';
        } elseif ($this->hasSentRequestTo($userId)) {
            return 'request_sent';
        } elseif ($this->hasPendingRequestFrom($userId)) {
            return 'request_received';
        }

        return 'none';
    }

    public function socialAccounts()
    {
        return $this->hasMany(SocialAccount::class);
    }

    public function getAvatarUrlAttribute()
    {
        if ($this->avatar) {
            return Storage::disk('public')->url($this->avatar);
        }

        return 'https://ui-avatars.com/api/?background=0D8ABC&color=fff&name=' . urlencode($this->username);
    }
}
