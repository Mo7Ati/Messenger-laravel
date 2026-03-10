<?php

namespace App\Http\Controllers;

use App\Http\Resources\ChatResource;
use App\Models\Chat;
use Auth;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ChatController extends Controller
{
    public function index()
    {
        $user = Auth::user();
        $chats = $user->chats()
            ->with([
                'participants',
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

        $chat = $user->chats()
            ->with([
                'participants',
                'messages.attachments',
            ])->findOrFail($id);

        return successResponse(
            ChatResource::make($chat),
            'Chat fetched successfully'
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

    public function destroy($id)
    {
    }
}
