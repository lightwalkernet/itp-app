<?php

use App\Support\Welcome;
use Illuminate\Support\Facades\Http;
use Itp\Core\Models\UniversalItp;
use Itp\Core\Models\User;

beforeEach(function () {
    Http::preventStrayRequests();
    $this->withoutVite();
});

it('sends a new guest from Today to the welcome', function () {
    UniversalItp::factory()->base()->create(['slug' => Welcome::INTRODUCTION_SLUG]);

    $this->get(route('home'))->assertRedirect(route('welcome'));
});

it('opens Today for a guest already welcomed on this browser', function () {
    UniversalItp::factory()->base()->create(['slug' => Welcome::INTRODUCTION_SLUG]);

    $this->withCookie(Welcome::COOKIE, '1')->get(route('home'))->assertOk();
});

it('opens Today for a guest already welcomed on this phone', function () {
    useMobileBuild()->items = [];
    UniversalItp::factory()->base()->create(['slug' => Welcome::INTRODUCTION_SLUG]);
    Welcome::markSeen();

    $this->get(route('home'))->assertOk();
});

it('never sends a signed-in user to the welcome', function () {
    UniversalItp::factory()->base()->create(['slug' => Welcome::INTRODUCTION_SLUG]);

    $this->actingAs(User::factory()->create())->get(route('home'))->assertOk();
});

it('skips the welcome, for good, when there is no Introduction to play', function () {
    $this->get(route('home'))
        ->assertOk()
        ->assertCookie(Welcome::COOKIE);
});
