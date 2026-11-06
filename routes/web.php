<?php

use App\Http\Controllers\MediaController;
use App\Http\Controllers\SignOutController;
use App\Http\Middleware\EnsureProfileIsComplete;
use App\Livewire\Account;
use App\Livewire\CompleteProfile;
use App\Livewire\CreateItp;
use App\Livewire\Home;
use App\Livewire\ShowGeneration;
use App\Livewire\ShowItp;
use App\Livewire\ShowUniversalItp;
use App\Livewire\SignIn;
use App\Livewire\UpdateRequired;
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
    Route::livewire('/', Home::class)->name('home');
    Route::livewire('universal/{universalItp:slug}', ShowUniversalItp::class)->name('universals.show');
});

Route::get('media/{key}', MediaController::class)->where('key', '.*')->name('media.show');

Route::middleware('auth')->group(function () {
    Route::livewire('welcome', CompleteProfile::class)->name('profile');
    Route::post('sign-out', SignOutController::class)->name('sign-out');

    Route::middleware(EnsureProfileIsComplete::class)->group(function () {
        Route::livewire('account', Account::class)->name('account');

        Route::livewire('itps/create', CreateItp::class)->name('itps.create');
        Route::livewire('itps/{itp}', ShowItp::class)->name('itps.show');
        Route::livewire('generations/{generationId}', ShowGeneration::class)->name('generations.show');
    });
});

Route::livewire('update-required', UpdateRequired::class)->name('update-required');
