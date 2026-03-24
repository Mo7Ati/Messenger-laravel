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
use Illuminate\Validation\Rule;

class ChatController extends Controller
{
    public function index()
    {
        $user = Auth::user();
        $data = request()->validate([
            'type' => ['nullable', Rule::enum(ChatTypeEnum::class)],
        ]);

        $chats = $user->chats()
            ->when($data['type'] ?? null, function ($query, $value) {
                $query->where('type', $value);
            })
            ->with([
                'participants',
                'lastMessage' => ['recipients', 'user'],
            ])
            ->withCount([
                'recipients' => fn($builder) => $builder->where('recipients.user_id', $user->id)->whereNull('recipients.read_at'),
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
                'recipients' => fn($builder) => $builder->where('recipients.user_id', $user->id)->whereNull('recipients.read_at'),
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
            $chat = Chat::create([
                'user_id' => $user->id,
                'label' => $request->post('label'),
                'type' => ChatTypeEnum::GROUP,
            ]);

            $chat->participants()->attach($participants_ids);
            $chat->participants()->attach($user->id, ['role' => 'admin']);

            $chat->load('participants');

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
