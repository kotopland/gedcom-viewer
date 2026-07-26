<?php

use App\Http\Controllers\GedcomController;

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


Route::middleware(['auth', 'verified'])->group(function () {
    Route::inertia('dashboard', 'Dashboard')->name('dashboard');
});

require __DIR__.'/settings.php';












