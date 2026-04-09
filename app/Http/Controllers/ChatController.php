<?php

namespace App\Http\Controllers;

use App\Enums\ChatTypeEnum;
use App\Events\AddedToGroup;
use App\Http\Resources\ChatResource;
use App\Models\Chat;
use Auth;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class ChatController extends Controller
{
    public function index()
    {
        $user = Auth::user();
        $request = request();

        $request->validate([
            'type' => ['nullable', Rule::enum(ChatTypeEnum::class)],
        ]);

        $chats = $user->chats()
            ->when($request->input('type'), function ($query, $value) {
                $query->where('type', $value);
            })
            ->with([
                'participants',
                'lastMessage' => ['recipients', 'user'],
            ])
            ->withCount([
                'recipients as unread_messages_count' => fn($builder) => $builder->where('recipients.user_id', $user->id)->whereNull('recipients.read_at'),
            ])
            ->get();

        return successResponse(
            ChatResource::collection($chats),
            'Chats fetched successfully'
        );
    }

    public function show($id)
    {
        $user = Auth::user();

        // retrieve the chat with the messages, attachments and user
        $chat = $user->chats()
            ->with([
                'participants',
                'messages' => ['attachments', 'user', 'recipients'],
            ])
            ->withCount([
                'recipients as unread_messages_count' => fn($builder) => $builder->where('recipients.user_id', $user->id)->whereNull('recipients.read_at'),
            ])
            ->findOrFail($id);

        return successResponse(
            ChatResource::make($chat),
            'Chat fetched successfully'
        );
    }

    public function store(Request $request)
    {
        $request->validate([
            'participants_ids' => ['required', 'array'],
            'avatar' => ['nullable', 'image', 'mimes:jpeg,png,jpg,gif,svg', 'max:2048'],
            'label' => ['required', 'string'],
        ]);

        $user = Auth::user();
        $participants_ids = $request->post('participants_ids', []);

        DB::beginTransaction();
        try {
            $chatData = [
                'user_id' => $user->id,
                'label' => $request->post('label'),
                'type' => ChatTypeEnum::GROUP,
            ];

            if ($request->hasFile('avatar')) {
                $chatData['avatar'] = $request->file('avatar')->store('group-avatars', 'public');
            }

            $chat = Chat::create($chatData);

            $chat->participants()->attach($participants_ids);
            $chat->participants()->attach($user->id, ['role' => 'admin']);

            $chat->load(['participants', 'lastMessage' => ['user']]);

            broadcast(new AddedToGroup($chat))->toOthers();

            DB::commit();
        } catch (Exception $e) {
            DB::rollBack();
            throw $e;
        }

        return successResponse(
            ChatResource::make($chat),
            'Group created successfully',
            201
        );
    }

    public function addParticipant(Request $request, Chat $chat)
    {
        $current_user = Auth::user();

        if (!$current_user->isAdminOfChat($chat)) {
            return errorResponse('You are not authorized to add participants to this chat', 403);
        }

        $request->validate([
            'user_id' => 'required|integer|exists:users,id',
        ]);

        $userId = $request->post('user_id');
        $chat->participants()->attach($userId);

        $chat->load(['participants', 'lastMessage' => ['user']]);

        broadcast(new AddedToGroup($chat, (int) $userId));

        return successResponse(
            null,
            'Participant added successfully',
            200
        );
    }

    public function removeParticipant(Chat $chat, $userId)
    {
        $current_user = Auth::user();

        if (!$current_user->isAdminOfChat($chat)) {
            return errorResponse('You are not authorized to remove participants from this chat', 403);
        }

        $chat->participants()->detach($userId);

        return successResponse(
            null,
            'Participant removed successfully',
            200
        );
    }

    public function updateAvatar(Request $request, Chat $chat)
    {
        $user = Auth::user();

        if (!$user->isAdminOfChat($chat)) {
            return errorResponse('You are not authorized to update this group avatar', 403);
        }

        $request->validate([
            'avatar' => ['required', 'image', 'mimes:jpeg,png,jpg,gif,svg', 'max:2048'],
        ]);

        $oldAvatar = $chat->getRawOriginal('avatar');
        if ($oldAvatar) {
            Storage::disk('public')->delete($oldAvatar);
        }

        $chat->update([
            'avatar' => $request->file('avatar')->store('group-avatars', 'public'),
        ]);

        $chat->load('participants');

        return successResponse(
            null,
            'Group avatar updated successfully'
        );
    }

    public function leaveGroup(Chat $chat)
    {
        $user = Auth::user();

        if ($chat->type !== ChatTypeEnum::GROUP) {
            return errorResponse('You can only leave group chats', 403);
        }

        if (!$chat->participants()->where('user_id', $user->id)->exists()) {
            return errorResponse('You are not a participant of this chat', 403);
        }

        if ($user->isAdminOfChat($chat) && $chat->participants()->count() > 1) {
            // Transfer admin role to the next participant
            $nextParticipant = $chat->participants()->where('user_id', '!=', $user->id)->first();
            $chat->participants()->updateExistingPivot($nextParticipant->id, ['role' => 'admin']);
        }

        $chat->participants()->detach($user->id);

        if ($chat->participants()->count() === 0) {
            $chat->delete();
        }

        return successResponse(
            null,
            'You have left the group successfully',
            200
        );
    }

    public function markAsRead($id)
    {
        $user = Auth::user();
        $chat = $user->chats()->findOrFail($id);

        $chat->recipients()
            ->whereNull('recipients.read_at')
            ->where('recipients.user_id', $user->id)
            ->update([
                'read_at' => now(),
            ]);

        return successResponse(
            null,
            'All Messages Read',
            200
        );
    }
}
