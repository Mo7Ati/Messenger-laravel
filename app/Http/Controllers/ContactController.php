<?php

namespace App\Http\Controllers;

use App\Enums\ConversationTypeEnum;
use App\Http\Requests\SendContactRequestRequest;
use App\Http\Resources\ConversationResource;
use App\Http\Resources\UserResource;
use App\Models\Contact;
use App\Models\Conversation;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ContactController extends Controller
{
    /**
     * Get all accepted contacts of the authenticated user.
     */
    public function index(Request $request): JsonResponse
    {
        $user = Auth::user();
        $contacts = $user->contacts();

        $data = $contacts->map(fn($contact) => [
            'id' => $contact->id,
            'name' => $contact->name,
            'username' => $contact->username,
            'avatar' => $contact->avatar,
        ]);

        return successResponse($data->values()->all(), 'Contacts retrieved successfully', 200);
    }

    /**
     * Get Contact Chat
     */
    public function show(Request $request, User $contact)
    {
        $user = Auth::user();
        $conversation = $user->conversations()
            ->with('messages')
            ->where('type', ConversationTypeEnum::PEER)
            ->whereHas('participants', function ($query) use ($contact, $user) {
                $query->where('user_id', $contact->id)
                    ->where('user_id', '<>', $user->id);
            })->first();

        return successResponse(
            [
                'chat' => $conversation ? ConversationResource::make($conversation) : null,
                'contact' => UserResource::make($contact),
            ],
            'Contact retrieved successfully',
            200
        );
    }

    /**
     * Get all pending contact requests received by the authenticated user.
     */
    public function pendingRequests(Request $request): JsonResponse
    {
        $user = Auth::user();
        $requests = $user->receivedContactRequests()->with('user:id,name,username,avatar')->get();

        $data = $requests->map(fn(Contact $contact) => [
            'id' => $contact->id,
            'user_id' => $contact->user_id,
            'contact_id' => $contact->contact_id,
            'status' => $contact->status,
            'created_at' => $contact->created_at,
            'user' => $contact->user ? [
                'id' => $contact->user->id,
                'name' => $contact->user->name,
                'username' => $contact->user->username,
                'avatar' => $contact->user->avatar,
            ] : null,
        ]);

        return successResponse($data->values()->all(), 'Pending requests retrieved', 200);
    }

    /**
     * Get all pending contact requests sent by the authenticated user.
     */
    public function sentRequests(Request $request): JsonResponse
    {
        $user = Auth::user();
        $requests = $user->sentContactRequests()->with('contact:id,name,username,avatar')->get();

        $data = $requests->map(fn(Contact $contact) => [
            'id' => $contact->id,
            'user_id' => $contact->user_id,
            'contact_id' => $contact->contact_id,
            'status' => $contact->status,
            'created_at' => $contact->created_at,
            'contact' => $contact->contact ? [
                'id' => $contact->contact->id,
                'name' => $contact->contact->name,
                'username' => $contact->contact->username,
                'avatar' => $contact->contact->avatar,
            ] : null,
        ]);

        return successResponse($data->values()->all(), 'Sent requests retrieved', 200);
    }

    /**
     * Send a contact request to another user.
     */
    public function sendRequest(SendContactRequestRequest $request): JsonResponse
    {
        $user = Auth::user();
        $contactId = (int) $request->validated('contact_id');

        if ($contactId === $user->id) {
            return errorResponse('You cannot add yourself as a contact', 400);
        }

        if ($user->isContactWith($contactId)) {
            return errorResponse('Already in contacts', 400);
        }

        if ($user->hasSentRequestTo($contactId)) {
            return errorResponse('Request already sent', 400);
        }

        if ($user->hasPendingRequestFrom($contactId)) {
            $incomingRequest = Contact::where('user_id', $contactId)
                ->where('contact_id', $user->id)
                ->where('status', 'pending')
                ->first();

            return $this->acceptRequest($request, $incomingRequest);
        }

        DB::transaction(function () use ($user, $contactId) {
            Contact::create([
                'user_id' => $user->id,
                'contact_id' => $contactId,
                'status' => 'pending',
                'action_user_id' => $user->id,
            ]);
            Contact::create([
                'user_id' => $contactId,
                'contact_id' => $user->id,
                'status' => 'pending',
                'action_user_id' => $user->id,
            ]);
        });

        return successResponse(null, 'Contact request sent successfully', 201);
    }

    /**
     * Accept a contact request (must be the recipient).
     */
    public function acceptRequest(Request $request, Contact $contact): JsonResponse
    {
        $user = Auth::user();

        if ((int) $contact->contact_id !== (int) $user->id) {
            return errorResponse('Unauthorized', 403);
        }

        DB::transaction(function () use ($contact) {
            Contact::where(function ($q) use ($contact) {
                $q->where('user_id', $contact->user_id)->where('contact_id', $contact->contact_id);
            })->orWhere(function ($q) use ($contact) {
                $q->where('user_id', $contact->contact_id)->where('contact_id', $contact->user_id);
            })->update(['status' => 'accepted']);
        });

        return successResponse(null, 'Contact request accepted', 200);
    }

    /**
     * Reject a contact request (must be the recipient).
     */
    public function rejectRequest(Request $request, Contact $contact): JsonResponse
    {
        $user = Auth::user();

        if ((int) $contact->contact_id !== (int) $user->id) {
            return errorResponse('Unauthorized', 403);
        }

        DB::transaction(function () use ($contact) {
            Contact::where(function ($q) use ($contact) {
                $q->where('user_id', $contact->user_id)->where('contact_id', $contact->contact_id);
            })->orWhere(function ($q) use ($contact) {
                $q->where('user_id', $contact->contact_id)->where('contact_id', $contact->user_id);
            })->delete();
        });

        return successResponse(null, 'Contact request rejected', 200);
    }

    /**
     * Remove a contact (must be one of the two users in the contact).
     */
    public function removeContact(Request $request, Contact $contact): JsonResponse
    {
        $user = Auth::user();
        $userId = (int) $user->id;

        if ((int) $contact->user_id !== $userId && (int) $contact->contact_id !== $userId) {
            return errorResponse('Unauthorized', 403);
        }

        DB::transaction(function () use ($contact) {
            Contact::where(function ($q) use ($contact) {
                $q->where('user_id', $contact->user_id)->where('contact_id', $contact->contact_id);
            })->orWhere(function ($q) use ($contact) {
                $q->where('user_id', $contact->contact_id)->where('contact_id', $contact->user_id);
            })->delete();
        });

        return successResponse(null, 'Contact removed', 200);
    }
}
