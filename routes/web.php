<?php

use App\Http\Controllers\MediaController;
use App\Http\Controllers\SignOutController;
use App\Http\Middleware\EnsureProfileIsComplete;
use App\Http\Middleware\EnsureWelcomed;
use App\Livewire\About;
use App\Livewire\CompleteProfile;
use App\Livewire\CreateItp;
use App\Livewire\DeleteAccount;
use App\Livewire\DesignSystem;
use App\Livewire\Home;
use App\Livewire\Library;
use App\Livewire\Player;
use App\Livewire\Prompt;
use App\Livewire\ShowGeneration;
use App\Livewire\ShowItp;
use App\Livewire\ShowUniversalItp;
use App\Livewire\SignIn;
use App\Livewire\UpdateRequired;
use App\Livewire\Welcome;
use App\Livewire\You;
use App\Support\PlayLink;
use Illuminate\Support\Facades\Route;

/*
 * Every screen is a real route and a full-page Livewire component (platform.md §8).
 */
Route::middleware('guest')->group(function () {
    Route::livewire('sign-in', SignIn::class)->name('sign-in');
});

/*
 * Open to guests too: '/' is NativePHP's start URL, and the universal ITPs show before sign-in.
 * A signed-in user without a first name is still sent to the profile screen first.
 */
Route::middleware(EnsureProfileIsComplete::class)->group(function () {
    Route::livewire('/', Home::class)->middleware(EnsureWelcomed::class)->name('home');
    Route::livewire('welcome', Welcome::class)->name('welcome');
    Route::livewire('play/{source}/{key}/{piece}', Player::class)
        ->whereIn('source', ['universal', 'itp'])
        ->whereIn('piece', PlayLink::PIECES)
        ->name('play');
    Route::livewire('universal/{universalItp:slug}', ShowUniversalItp::class)->name('universals.show');
    Route::livewire('itps', Library::class)->name('itps.index');
    Route::livewire('about', About::class)->name('about');
});

/*
 * Account deletion (platform.md §5): a page on the web build, for a guest or anyone signed in, so
 * no first name is needed. A fresh email code is the identity check. The phone links here.
 */
Route::livewire('delete-account', DeleteAccount::class)->name('account.delete');
Route::redirect('account', '/you');

Route::get('media/{key}', MediaController::class)->where('key', '.*')->name('media.show');

Route::middleware('auth')->group(function () {
    Route::livewire('profile', CompleteProfile::class)->name('profile');
    Route::post('sign-out', SignOutController::class)->name('sign-out');

    Route::middleware(EnsureProfileIsComplete::class)->group(function () {
        Route::livewire('you', You::class)->name('you');

        Route::livewire('today/{kind}', Prompt::class)->whereIn('kind', ['morning', 'step', 'evening', 'evidence'])->name('today.prompt');

        Route::livewire('itps/create', CreateItp::class)->name('itps.create');
        Route::livewire('itps/{itp}', ShowItp::class)->name('itps.show');
        Route::livewire('generations/{generationId}', ShowGeneration::class)->name('generations.show');
    });
});

Route::livewire('update-required', UpdateRequired::class)->name('update-required');

/*
 * The design system on one screen (tokens, the field, the bar, components). Local only, so it
 * never reaches Forge or the phone build.
 */
if (app()->environment('local')) {
    Route::livewire('design', DesignSystem::class)->name('design');
}
