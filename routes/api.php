<?php

use App\Http\Controllers\ContactController;
use App\Http\Controllers\ConversationController;
use App\Http\Controllers\GroupsController;
use App\Http\Controllers\MessagesController;
use App\Http\Controllers\UserController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::any('/user', function (Request $request) {
    return successResponse($request->user());
})->middleware('auth:sanctum');

Route::middleware('auth:sanctum')->group(function () {
    Route::get('conversations', [ConversationController::class, 'index']);
    Route::get('conversations/{conversation}', [ConversationController::class, 'show']);
    // Route::post('conversations/{conversation}/participants', [ConversationController::class, 'addParticipant']);
    // Route::delete('conversations/{conversation}/participants', [ConversationController::class, 'removeParticipant']);

    // Route::get('conversations/{id}/messages', [MessagesController::class, 'index']);
    Route::post('messages', [MessagesController::class, 'store']);
    Route::get('messages/attachments/{attachment}', [MessagesController::class, 'downloadAttachment'])
        ->name('messages.attachments.download');
    // Route::delete('messages/{id}', [MessagesController::class, 'destroy']);

    // Route::post('messages/{id}/read', [MessagesController::class, 'markAsRead']);

    Route::get('groups', [GroupsController::class, 'index']);
    Route::post('groups', [GroupsController::class, 'store']);

    Route::get('/contacts/search', [UserController::class, 'search']);

    Route::get('/contacts', [ContactController::class, 'index']);
    Route::get('/contacts/requests', [ContactController::class, 'pendingRequests']);
    Route::get('/contacts/sent', [ContactController::class, 'sentRequests']);

    Route::get('/contacts/{contact}', [ContactController::class, 'show']);

    Route::post('/contacts/request', [ContactController::class, 'sendRequest']);

    Route::post('/contacts/accept/{user}', [ContactController::class, 'acceptRequest']);
    Route::post('/contacts/reject/{user}', [ContactController::class, 'rejectRequest']);
    Route::delete('/contacts/{user}', [ContactController::class, 'removeContact']);
});
