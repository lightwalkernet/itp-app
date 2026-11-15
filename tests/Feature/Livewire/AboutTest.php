<?php

use App\Livewire\About;
use Itp\Core\Models\User;

beforeEach(function () {
    $this->withoutVite();
});

it('quotes the Creative Codex’s description of ITP, word for word', function () {
    $this->get(route('about'))
        ->assertOk()
        ->assertSeeLivewire(About::class)
        ->assertSee('data-room="white"', escape: false)
        ->assertSeeInOrder([
            'ITP is not content. It is a field.',
            'A transmission of possibility, presence and gentle expansion that lands as real. Grounded and quietly extraordinary, ITP does not try to convince people into a better life. It helps them return to the part of themselves that already knows more is possible — and then creates the inner conditions for that knowing to become embodied.',
        ]);
});

it('leads back to You when signed in, and to Today for a guest', function () {
    $this->get(route('about'))->assertSee('href="/" data-transition="back"', escape: false);

    $this->actingAs(User::factory()->create())
        ->get(route('about'))
        ->assertSee('href="/you" data-transition="back"', escape: false);
});
