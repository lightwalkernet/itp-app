<?php

use App\Livewire\Welcome;
use App\Support\Welcome as FirstLaunch;
use Itp\Core\Models\UniversalItp;
use Itp\Core\Models\User;

beforeEach(function () {
    $this->withoutVite();
});

it('welcomes a new guest, and enters the field through the Introduction', function () {
    UniversalItp::factory()->base()->create(['slug' => FirstLaunch::INTRODUCTION_SLUG]);

    $this->get(route('welcome'))
        ->assertOk()
        ->assertSeeLivewire(Welcome::class)
        ->assertSee('data-room="light" data-bar="hidden" class="welcome"', escape: false)
        ->assertSeeInOrder(['It’s', 'totally', 'possible', '<a href="/play/universal/introduction/itp?from=welcome" data-transition="forward"', 'Enter the Field'], escape: false);
});

it('sends anyone who shouldn\'t see it on to Today', function () {
    UniversalItp::factory()->base()->create(['slug' => FirstLaunch::INTRODUCTION_SLUG]);

    $this->actingAs(User::factory()->create())->get(route('welcome'))->assertRedirect(route('home'));
    auth()->logout();
    $this->withCookie(FirstLaunch::COOKIE, '1')->get(route('welcome'))->assertRedirect(route('home'));
});

it('skips itself, for good, when there is no Introduction to play', function () {
    $this->get(route('welcome'))
        ->assertRedirect(route('home'))
        ->assertCookie(FirstLaunch::COOKIE);
});
