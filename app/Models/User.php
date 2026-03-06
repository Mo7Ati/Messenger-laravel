<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
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
        'name',
        'email',
        'password',
        'last_active_at',
        'username',
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

    public function conversation()
    {
        return $this->hasOne(Conversation::class);
    }

    public function conversations()
    {
        return $this->belongsToMany(Conversation::class, 'participants')
            ->latest('last_message_id')
            ->withPivot(['role', 'joined_at']);
    }

    public function sentMessages()
    {
        return $this->hasMany(Message::class);
    }

    public function receivedMessages()
    {
        return $this->belongsToMany(Message::class, 'recipients')
            ->withPivot(['read_at', 'deleted_at']);
    }

    public function acceptedContactsAsUser(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'contacts', 'user_id', 'contact_id')
            ->wherePivot('status', 'accepted');
    }

    public function acceptedContactsAsContact(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'contacts', 'contact_id', 'user_id')
            ->wherePivot('status', 'accepted');
    }

    public function sentContactRequests(): HasMany
    {
        return $this->hasMany(Contact::class, 'user_id')->where('status', 'pending');
    }

    public function receivedContactRequests(): HasMany
    {
        return $this->hasMany(Contact::class, 'contact_id')->where('status', 'pending');
    }

    /**
     * Get all accepted contacts (users) for this user.
     *
     * @return Collection<int, User>
     */
    public function contacts(): Collection
    {
        $contactIds = Contact::where('user_id', $this->id)->where('status', 'accepted')->pluck('contact_id');
        $userIds = Contact::where('contact_id', $this->id)->where('status', 'accepted')->pluck('user_id');

        return User::whereIn('id', $contactIds->merge($userIds))->get();
    }

    public function isContactWith(int $userId): bool
    {
        return Contact::where(function ($query) use ($userId) {
            $query->where('user_id', $this->id)->where('contact_id', $userId);
        })->orWhere(function ($query) use ($userId) {
            $query->where('user_id', $userId)->where('contact_id', $this->id);
        })->where('status', 'accepted')->exists();
    }

    public function hasPendingRequestFrom(int $userId): bool
    {
        return Contact::where('user_id', $userId)
            ->where('contact_id', $this->id)
            ->where('status', 'pending')
            ->exists();
    }

    public function hasSentRequestTo(int $userId): bool
    {
        return Contact::where('user_id', $this->id)
            ->where('contact_id', $userId)
            ->where('status', 'pending')
            ->exists();
    }

    public function getAvatarUrlAttribute()
    {
        return url('https://ui-avatars.com/api/?background=0D8ABC&color=fff&name=' . $this->name);
    }
}
