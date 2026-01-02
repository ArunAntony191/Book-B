<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\MessageController;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::get('/messages', [MessageController::class, 'index']);
Route::post('/messages', [MessageController::class, 'store']);
Route::get('/contacts/{userId}', [MessageController::class, 'contacts']);
Route::get('/unread/{userId}', [MessageController::class, 'unreadCount']);
Route::get('/unread-by-sender/{userId}', [MessageController::class, 'unreadBySender']);
