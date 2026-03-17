<?php

namespace App\Http\Controllers;

use App\Enums\ChatTypeEnum;
use App\Events\GroupCreated;
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
                'participants' => fn($query) => $query->where('user_id', '<>', $user->id),
                'lastMessage.recipients',
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
                'participants' => fn($query) => $query->where('user_id', '<>', $user->id),
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

            $chat->participants()->attach([...$participants_ids, $user->id]);

            $chat->load(['participants' => fn($query) => $query->where('user_id', '<>', $user->id)]);

            broadcast(new GroupCreated($chat))->toOthers();
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
        $request->validate([
            'user_id' => 'required|integer|exists:users,id',
        ]);

        $chat->participants()->attach($request->post('user_id'));
    }

    public function removeParticipant(Request $request, Chat $chat)
    {
        $request->validate([
            'user_id' => 'required|integer|exists:users,id',
        ]);

        $chat->participants()->detach($request->post('user_id'));
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

        // DB::statement('
        //     UPDATE recipients
        //     inner join messages on messages.id = recipients.message_id
        //     SET recipients.read_at = ?
        //     where recipients.user_id = ?
        //           and messages.chat_id = ?
        //           and recipients.read_at is null
        // ', [now(), $user->id, $id]);

        return successResponse(
            null,
            'All Messages Read',
            200
        );
    }
}
