<?php

use Illuminate\Support\Facades\Route;
use App\Livewire\Chat\Index;
use App\Livewire\Chat\Chat;
use App\Livewire\Users;

Route::view('/', 'welcome');

Route::view('profile', 'profile')
    ->middleware(['auth'])
    ->name('profile');

require __DIR__.'/auth.php';
Route::middleware('auth')->group(function (){
Route::get('/chat',Index::class)->name('chat.index');
Route::get('/dashboard',Users::class)->name('dashboard');
});
Route::get('/chat/{query}', Chat::class)
    ->name('chat')
    ->middleware(['auth', 'authorize.conversation']);