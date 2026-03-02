<?php

namespace App\Http\Controllers;

use App\Enums\ConversationTypeEnum;
use App\Http\Resources\ConversationResource;
use App\Http\Resources\MessageResource;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\Recipient;
use App\Models\User;
use Auth;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ConversationController extends Controller
{
    public function index()
    {
        $user = Auth::user();
        $conversations = Conversation::query()
            ->withWhereHas('participants', function ($builder) use ($user) {
                return $builder->where('user_id', '<>', $user->id);
            })
            ->with([
                'lastMessage.recipients' => function ($builder) use ($user) {
                    return $builder->where('user_id', '<>', $user->id);
                },
            ])
            ->withCount([
                'recipients' => function ($builder) {
                    return $builder->whereNull('read_at');
                }
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
                'participants' => function ($builder) use ($user) {
                    return $builder->where('user_id', '<>', $user->id);
                },
                'messages'
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
