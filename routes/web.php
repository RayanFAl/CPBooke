<?php

use App\Http\Controllers\CustomerSupportChatController;
use App\Http\Controllers\ProfileController;
use App\Modules\Content\Http\Controllers\AppDownloadController;
use App\Modules\Content\Http\Controllers\PublicContentPageController;
use App\Modules\Content\Support\ContentPageCatalog;
use App\Modules\Support\Http\Controllers\SupportAttachmentDownloadController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    if (! auth()->check()) {
        return redirect()->route('login');
    }

    return redirect()->route(auth()->user()->homeRouteName());
});

Route::prefix('pages')->as('content.pages.')->controller(PublicContentPageController::class)->group(function (): void {
    Route::get('/', 'index')->name('index');
    Route::get('/product/{product}', 'showForProduct')
        ->name('product')
        ->whereIn('product', ContentPageCatalog::products());
    Route::get('/{slug}', 'show')
        ->name('show')
        ->where('slug', '[a-z0-9]+(?:-[a-z0-9]+)*');
});

Route::redirect('/privacy-policy', '/pages/privacy-policy');
Route::redirect('/terms', '/pages/terms-of-service');

Route::prefix('app')->as('app.')->controller(AppDownloadController::class)->group(function (): void {
    Route::get('/', 'show')->name('download.page');
    Route::get('/download', 'download')->name('download.file');
});

Route::redirect('/download', '/app');

Route::get('/support/attachments/{message}', SupportAttachmentDownloadController::class)
    ->middleware(['signed', 'throttle:60,1'])
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
