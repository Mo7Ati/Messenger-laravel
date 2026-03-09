<?php

namespace App\Http\Controllers;

use App\Http\Resources\ConversationResource;
use App\Models\Conversation;
use Auth;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ConversationController extends Controller
{
    public function __construct()
    {
        DB::listen(function ($query): void {
            Log::info('ConversationController query', [
                'sql' => $query->sql,
                'bindings' => $query->bindings,
                'time_ms' => $query->time,
            ]);
        });
    }

    public function index()
    {
        $user = Auth::user();
        $conversations = $user->conversations()
            ->with([
                'participants',
                'lastMessage.recipients' => fn($builder) => $builder->where('user_id', '<>', $user->id),
            ])
            ->withCount([
                'recipients' => fn($builder) => $builder->where('recipients.user_id', $user->id)->whereNull('recipients.read_at'),
            ])
            ->get();

        return successResponse(
            ConversationResource::collection($conversations),
            'Conversations fetched successfully'
        );
    }

    public function show($id)
    {
        $user = Auth::user();

        $conversation = $user->conversations()
            ->with([
                'participants',
                'messages',
            ])->findOrFail($id);

        return successResponse(
            ConversationResource::make($conversation),
            'Conversation fetched successfully'
        );
    }

    public function addParticipant(Request $request, Conversation $conversation)
    {
        $request->validate([
            'user_id' => 'required|integer|exists:users,id',
        ]);

        $conversation->participants()->attach($request->post('user_id'));
    }

    public function removeParticipant(Request $request, Conversation $conversation)
    {
        $request->validate([
            'user_id' => 'required|integer|exists:users,id',
        ]);

        $conversation->participants()->detach($request->post('user_id'));
    }

    public function destroy($id)
    {
    }
}
