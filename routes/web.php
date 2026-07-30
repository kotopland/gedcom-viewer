<?php

use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\GedcomController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth'])->group(function () {
    Route::inertia('/pending-verification', 'auth/AwaitingVerification')->name('verification.pending');
});

Route::middleware(['auth', 'superuser.verified'])->group(function () {
    Route::inertia('/', 'Welcome')->name('home');

    Route::get('/gedcom', [GedcomController::class, 'index'])->name('gedcom.index');
    Route::get('/storage/gedcom/media/{filename}', [GedcomController::class, 'serveMedia'])->where('filename', '.*')->name('gedcom.storage.media');
    Route::get('/gedcom/media-file/{filename}', [GedcomController::class, 'serveMedia'])->where('filename', '.*')->name('gedcom.media.file');

    Route::prefix('api/gedcom')->group(function () {
        Route::get('/search', [GedcomController::class, 'search'])->name('gedcom.api.search');
        Route::get('/person/{id}', [GedcomController::class, 'person'])->name('gedcom.api.person');
        Route::get('/tree/{id}', [GedcomController::class, 'tree'])->name('gedcom.api.tree');
        Route::get('/media', [GedcomController::class, 'media'])->name('gedcom.api.media');
        Route::match(['get', 'post'], '/reimport', [GedcomController::class, 'reimport'])->name('gedcom.api.reimport');
    });

    Route::inertia('dashboard', 'Dashboard')->name('dashboard');
});

Route::middleware(['auth', 'superuser.verified', 'superuser'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/users', [UserController::class, 'index'])->name('users.index');
    Route::patch('/users/{user}/verify', [UserController::class, 'verify'])->name('users.verify');
    Route::patch('/users/{user}/unverify', [UserController::class, 'unverify'])->name('users.unverify');
    Route::patch('/users/{user}/toggle-superuser', [UserController::class, 'toggleSuperuser'])->name('users.toggle-superuser');
    Route::delete('/users/{user}', [UserController::class, 'destroy'])->name('users.destroy');
});

require __DIR__.'/settings.php';

if (class_exists(\Spatie\MailPreview\MailPreviewServiceProvider::class)) {
    Route::mailPreview();
}
