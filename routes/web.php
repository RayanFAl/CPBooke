<?php

use App\Http\Controllers\CustomerSupportChatController;
use App\Http\Controllers\ProfileController;
use App\Modules\Support\Http\Controllers\SupportAttachmentDownloadController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    if (! auth()->check()) {
        return redirect()->route('login');
    }

    return redirect()->route(auth()->user()->homeRouteName());
});

Route::get('/support/attachments/{message}', SupportAttachmentDownloadController::class)
    ->middleware('signed')
    ->whereNumber('message')
    ->name('support.attachments.download');

Route::middleware('auth')->group(function () {
    Route::get('/support/chat', CustomerSupportChatController::class)->name('customer.support.chat');
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/admin.php';
require __DIR__.'/auth.php';
