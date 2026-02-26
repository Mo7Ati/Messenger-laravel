<?php

namespace App\Http\Controllers;

use App\Models\Conversation;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class MessengerController extends Controller
{
    public function index()
    {
        // $user = Auth::user();
        // $friends = User::where('id', '<>', 'id')->get();
        // $chats = $user->conversations()->with([
        //     'lastMessage',
        //     'participants' => function ($builder) use ($user) {
        //         $builder->where('id', '<>', $user->id);
        //     },
        // ])->get();

        return view('messenger',
        //  [
        //     'friends' => $friends,
        //     'chats' => $chats,
        // ],
    );
    }
}
