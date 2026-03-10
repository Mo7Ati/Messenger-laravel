<?php

namespace App\Http\Controllers;

use App\Enums\ChatTypeEnum;
use App\Http\Resources\ChatResource;
use App\Models\Chat;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class GroupsController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $user = Auth::user();
        $groups = $user->conversations()
            ->where('type', ConversationTypeEnum::GROUP)
            ->with([
                'participants',
                'lastMessage.recipients'
            ])
            ->withCount([
                'recipients' => fn($builder) => $builder->where('recipients.user_id', $user->id)->whereNull('recipients.read_at'),
            ])->get();

        return successResponse(
            ConversationResource::collection($groups),
            'Groups fetched successfully'
        );

    }

    /**
     * Store a newly created resource in storage.
     */
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
            $conversation = Conversation::create([
                'user_id' => $user->id,
                'label' => $request->post('label'),
                'type' => ConversationTypeEnum::GROUP
            ]);

            $conversation->participants()->attach([...$participants_ids, $user->id]);

            DB::commit();
        } catch (Exception $e) {
            DB::rollBack();
            throw $e;
        }
        // [
        //     'group' =>
        //         $conversation->load([
        //             'participants' => function ($query) use ($user) {
        //                 return $query->where('user_id', '<>', $user->id);
        //             },
        //             'lastMessage'
        //         ]),
        // ];

        return successResponse(
            ConversationResource::make($conversation),
            'Group created successfully',
            201
        );


    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $user = Auth::user();
        $group = $user->conversations()
            ->where('type', ConversationTypeEnum::GROUP)
            ->with([
                'participants',
                'messages.user',
                'messages.attachments',
            ])
            ->findOrFail($id);

        return successResponse(
            ConversationResource::make($group),
            'Group fetched successfully'
        );
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }
}
