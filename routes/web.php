<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ChatController;

Route::get('/', function () {
    return redirect()->route('chat');
});

Route::get('/chat/{id?}', [ChatController::class, 'chat'])->name('chat')->middleware('auth');

Route::get('/login', function () {
    return view('login');
})->name('loginPage');

Route::post('/login', [AuthController::class, 'login'])->name('login');
Route::post('/messages/send', [ChatController::class, 'sendMessage'])->name('messages.send');
Route::get('/users', [ChatController::class, 'getAllUsers']);
Route::post('/users/{id}/update-last-seen', [ChatController::class, 'updateLastSeen']);
Route::post('/message/mark', [ChatController::class, 'markAsDelivered'])->name('message.mark');
Route::post('/message/mark/read', [ChatController::class, 'markAsRead'])->name('message.mark.read');
