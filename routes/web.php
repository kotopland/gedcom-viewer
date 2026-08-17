<?php

use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Auth\MagicLinkController;
use App\Http\Controllers\GedcomController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::middleware(['throttle:6,1'])->group(function () {
    Route::post('/login/magic-link', [MagicLinkController::class, 'send'])->name('magic-link.send');
    Route::get('/login/magic-link/verify/{user}', [MagicLinkController::class, 'verify'])
        ->middleware(['signed'])
        ->name('magic-link.verify');
});

Route::middleware(['auth'])->group(function () {
    Route::get('/pending-verification', function (Request $request) {
        $user = $request->user();
        return Inertia::render('auth/AwaitingVerification', [
            'isVerified' => (bool) $user->is_verified,
            'hasStartPerson' => ! empty($user->start_person_id),
            'startPersonId' => $user->start_person_id,
        ]);
    })->name('verification.pending');
});

Route::middleware(['auth', 'superuser.verified'])->group(function () {
    Route::get('/', function (Request $request) {
        if ($request->user()->isSuperuser()) {
            return redirect()->route('dashboard');
        }
        return redirect()->route('gedcom.index');
    })->name('home');

    Route::get('/gedcom', [GedcomController::class, 'index'])->name('gedcom.index');
    Route::get('/storage/gedcom/media/{filename}', [GedcomController::class, 'serveMedia'])->where('filename', '.*')->name('gedcom.storage.media');
    Route::get('/storage/contributions/{filename}', [GedcomController::class, 'serveContributionMedia'])->where('filename', '.*')->name('gedcom.storage.contributions');
    Route::get('/gedcom/media-file/{filename}', [GedcomController::class, 'serveMedia'])->where('filename', '.*')->name('gedcom.media.file');

    Route::prefix('api/gedcom')->group(function () {
        Route::get('/search', [GedcomController::class, 'search'])->name('gedcom.api.search');
        Route::get('/stats', [GedcomController::class, 'stats'])->name('gedcom.api.stats');
        Route::get('/person/{id}', [GedcomController::class, 'person'])->name('gedcom.api.person');
        Route::post('/person/{id}/contribution', [GedcomController::class, 'submitContribution'])->name('gedcom.api.person.contribution');
        Route::get('/tree/{id}', [GedcomController::class, 'tree'])->name('gedcom.api.tree');
        Route::get('/lineage/{id}', [GedcomController::class, 'lineage'])->name('gedcom.api.lineage');
        Route::get('/media', [GedcomController::class, 'media'])->name('gedcom.api.media');
    });

});

// Admin / Superuser-only routes
Route::middleware(['auth', 'superuser.verified', 'superuser'])->group(function () {
    Route::inertia('dashboard', 'Dashboard')->name('dashboard');
    Route::post('/api/gedcom/reimport', [GedcomController::class, 'reimport'])->name('gedcom.api.reimport');
    Route::post('/api/gedcom/upload', [GedcomController::class, 'uploadGedcom'])->name('gedcom.api.upload');

    Route::prefix('admin')->name('admin.')->group(function () {
        Route::get('/users', [UserController::class, 'index'])->name('users.index');
        Route::patch('/users/{user}/verify', [UserController::class, 'verify'])->name('users.verify');
        Route::patch('/users/{user}/unverify', [UserController::class, 'unverify'])->name('users.unverify');
        Route::patch('/users/{user}/toggle-superuser', [UserController::class, 'toggleSuperuser'])->name('users.toggle-superuser');
        Route::patch('/users/{user}/start-person', [UserController::class, 'updateStartPerson'])->name('users.start-person');
        Route::delete('/users/{user}', [UserController::class, 'destroy'])->name('users.destroy');
    });
});

require __DIR__.'/settings.php';

if (class_exists(\Spatie\MailPreview\MailPreviewServiceProvider::class)) {
    Route::mailPreview();
}
