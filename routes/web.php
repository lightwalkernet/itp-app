<?php

use App\Http\Controllers\SignOutController;
use App\Http\Middleware\EnsureProfileIsComplete;
use App\Livewire\Account;
use App\Livewire\CompleteProfile;
use App\Livewire\Home;
use App\Livewire\SignIn;
use App\Livewire\UpdateRequired;
use Illuminate\Support\Facades\Route;

/*
 * Every screen is a real route and a full-page Livewire component (platform.md §8).
 */
Route::middleware('guest')->group(function () {
    Route::livewire('sign-in', SignIn::class)->name('sign-in');
});

Route::middleware('auth')->group(function () {
    Route::livewire('welcome', CompleteProfile::class)->name('profile');
    Route::post('sign-out', SignOutController::class)->name('sign-out');

    Route::middleware(EnsureProfileIsComplete::class)->group(function () {
        Route::livewire('/', Home::class)->name('home');
        Route::livewire('account', Account::class)->name('account');
    });
});

Route::livewire('update-required', UpdateRequired::class)->name('update-required');
