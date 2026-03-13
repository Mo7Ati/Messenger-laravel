<?php

namespace App\Http\Controllers;

use App\Enums\ChatTypeEnum;
use App\Enums\ContactStatusEnum;
use App\Events\ContactRequestSent;
use App\Events\ContactRequestUpdated;
use App\Http\Requests\SendContactRequestRequest;
use App\Http\Resources\ChatResource;
use App\Http\Resources\UserResource;
use App\Models\Contact;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ContactController extends Controller
{
    /**
     * Get all accepted contacts of the authenticated user.
     */
    public function index(Request $request)
    {
        $user = Auth::user();

        return successResponse(
            UserResource::collection($user->contacts()->get()),
            'Contacts retrieved successfully',
            200,
            [
                'pending_requests' => $user->contactsReceived()->wherePivot('status', ContactStatusEnum::PENDING)->count(),
            ],
        );
    }

    /**
     * Get Contact Chat
     */
    public function show(Request $request, User $contact)
    {
        $user = Auth::user();
        $chat = $user->chats()
            ->where('type', ChatTypeEnum::PEER)
            ->whereHas('participants', function ($query) use ($contact, $user) {
                $query->where('user_id', $contact->id)
                    ->where('user_id', '<>', $user->id);
            })
            ->with('messages.attachments')
            ->first();

        return successResponse(
            [
                'chat' => $chat ? ChatResource::make($chat) : null,
                'contact' => UserResource::make($contact),
            ],
            'Contact retrieved successfully',
            200
        );
    }

    /**
     * Get all pending contact requests received by the authenticated user.
     */
    public function pendingRequests(Request $request)
    {
        $user = Auth::user();
        $pendingContacts = $user->contactsReceived()
            ->where('contacts.status', ContactStatusEnum::PENDING)
            ->get();

        return successResponse(
            UserResource::collection($pendingContacts),
            'Pending requests retrieved',
            200,
        );
    }

    /**
     * Get all pending contact requests sent by the authenticated user.
     */
    public function sentRequests(Request $request): JsonResponse
    {
        $user = Auth::user();
        $sentContacts = $user->contactsSent()
            ->where('contacts.status', ContactStatusEnum::PENDING)
            ->get();

        return successResponse(
            UserResource::collection($sentContacts),
            'Sent requests retrieved',
            200
        );
    }

    /**
     * Send a contact request to another user.
     */
    public function sendRequest(SendContactRequestRequest $request): JsonResponse
    {
        $user = Auth::user();
        $receiverId = (int) $request->validated('receiver_id');

        if ($receiverId === $user->id) {
            return errorResponse('You cannot add yourself as a contact', 400);
        }

        if ($user->isContactWith($receiverId)) {
            return errorResponse('Already in contacts', 400);
        }

        if ($user->hasSentRequestTo($receiverId)) {
            return errorResponse('Request already sent', 400);
        }

        if ($user->hasPendingRequestFrom($receiverId)) {
            return $this->acceptRequest($request, $receiverId);
        }

        $contact = Contact::create([
            'sender_id' => $user->id,
            'receiver_id' => $receiverId,
            'status' => 'pending',
        ]);

        ContactRequestSent::dispatch($contact);

        return successResponse(null, 'Contact request sent successfully', 201);
    }

    /**
     * Accept a contact request (must be the recipient).
     */
    public function acceptRequest(Request $request, int $userId): JsonResponse
    {
        $user = Auth::user();
        $contact = Contact::query()
            ->where([
                'sender_id' => $userId,
                'receiver_id' => $user->id,
                'status' => ContactStatusEnum::PENDING,
            ])->firstOrFail();

        $contact->update([
            'status' => ContactStatusEnum::ACCEPTED,
            'accepted_at' => now(),
        ]);

        ContactRequestUpdated::dispatch($contact);

        return successResponse(
            $contact,
            'Contact request accepted',
            200
        );
    }

    /**
     * Reject a contact request (must be the recipient).
     */
    public function rejectRequest(Request $request, int $userId): JsonResponse
    {
        $user = Auth::user();

        $contact = Contact::query()
            ->where([
                'sender_id' => $userId,
                'receiver_id' => $user->id,
                'status' => ContactStatusEnum::PENDING,
            ])->firstOrFail();

        $contact->update([
            'status' => ContactStatusEnum::CANCELLED,
        ]);

        ContactRequestUpdated::dispatch($contact);

        return successResponse(
            $contact,
            'Contact request rejected',
            200
        );
    }

    /**
     * Remove a contact (must be one of the two users in the contact).
     */
    public function removeContact(Request $request, int $userId): JsonResponse
    {
        $authId = Auth::id();

        $contact = Contact::query()
            ->where(function ($q) use ($authId, $userId) {
                $q->where('sender_id', $authId)
                    ->where('receiver_id', $userId);
            })
            ->orWhere(function ($q) use ($authId, $userId) {
                $q->where('sender_id', $userId)
                    ->where('receiver_id', $authId);
            })
            ->where('status', ContactStatusEnum::ACCEPTED)
            ->firstOrFail();

        $contact->update([
            'status' => ContactStatusEnum::REMOVED,
        ]);

        return successResponse(
            $contact,
            'Contact removed',
            200
        );
    }
}
