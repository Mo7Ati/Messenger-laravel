<?php

namespace App\Events;

use App\Http\Resources\UserResource;
use App\Models\Contact;
use App\Models\User;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ContactRequestSent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public Contact $contact) {}

    /**
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('messenger.user.'.$this->contact->receiver_id),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        /** @var User|null $sender */
        $sender = User::query()->find($this->contact->sender_id);

        return [
            'contact_request' => [
                'id' => $this->contact->id,
                'sender' => $sender ? UserResource::make($sender)->resolve() : null,
                'receiver_id' => $this->contact->receiver_id,
                'status' => $this->contact->status,
            ],
        ];
    }
}
