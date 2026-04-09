<?php

use App\Http\Controllers\ChatController;
use App\Http\Controllers\ContactController;
use App\Http\Controllers\MessagesController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\UserController;
use App\Http\Resources\UserResource;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    $user = $request->user();

    return successResponse(UserResource::make($user));
})->middleware('auth:sanctum');

Route::middleware('auth:sanctum')->group(function () {
    // Chat routes
    Route::get('chats', [ChatController::class, 'index']);
    Route::get('chats/{chat}', [ChatController::class, 'show']);
    Route::post('chats', [ChatController::class, 'store']);
    Route::post('chats/{chat}/mark-as-read', [ChatController::class, 'markAsRead']);
    Route::post('chats/{chat}/participants', [ChatController::class, 'addParticipant']);
    Route::delete('chats/{chat}/participants/{userId}', [ChatController::class, 'removeParticipant']);
    Route::post('chats/{chat}/avatar', [ChatController::class, 'updateAvatar']);
    Route::post('chats/{chat}/leave', [ChatController::class, 'leaveGroup']);

    // Messages routes
    Route::post('messages', [MessagesController::class, 'store']);
    Route::get('messages/attachments/{attachment}', [MessagesController::class, 'downloadAttachment'])
        ->name('messages.attachments.download');

    // Contacts routes
    Route::get('/contacts/search', [UserController::class, 'search']);
    Route::get('/contacts', [ContactController::class, 'index']);
    Route::get('/contacts/requests', [ContactController::class, 'pendingRequests']);
    Route::get('/contacts/sent', [ContactController::class, 'sentRequests']);
    Route::get('/contacts/{contact}', [ContactController::class, 'show']);
    Route::post('/contacts/request', [ContactController::class, 'sendRequest']);
    Route::post('/contacts/accept/{user}', [ContactController::class, 'acceptRequest']);
    Route::post('/contacts/reject/{user}', [ContactController::class, 'rejectRequest']);
    Route::delete('/contacts/{user}', [ContactController::class, 'removeContact']);

    // Profile routes
    Route::post('/user/profile', [ProfileController::class, 'updateProfile']);
    Route::delete('/user/avatar', [ProfileController::class, 'deleteAvatar']);
    Route::put('/user/password', [ProfileController::class, 'updatePassword']);
});
