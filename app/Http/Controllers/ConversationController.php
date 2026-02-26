<?php

namespace App\Http\Controllers;

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
        $conversations = $user->conversations()
            ->with([
                'participants' => function ($builder) use ($user) {
                    return $builder->where('user_id', '<>', $user->id);
                },
                'lastMessage',
            ])->withCount([
                    'recipients as new_messages' => function ($builder) use ($user) {
                        $builder->where('recipients.user_id', '=', $user->id)
                            ->whereNull('read_at');
                    }
                ])
            ->get();

        $friends = User::where('id', '<>', $user->id)
            ->orderBy('name')
            ->get();

        $groups = $user->conversations()->where('type', 'group')->with([
            'participants' => function ($builder) use ($user) {
                return $builder->where('user_id', '<>', $user->id);
            },
            'lastMessage',
        ])->get();


        return
            [
                'chats' => $conversations,
                'friends' => $friends,
                'groups' => $groups,
            ]
        ;
    }

    public function show($id)
    {
        $user = Auth::user();
        return $user->conversations()->findOrFail($id)
            ->load([
                'participants' => function ($builder) use ($user) {
                    return $builder->where('user_id', '<>', $user->id);
                },
                'lastMessage',
            ]);
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
