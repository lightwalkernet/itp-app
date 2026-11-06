<?php

use App\Livewire\ShowItp;
use Itp\Core\Models\Itp;
use Itp\Core\Models\User;

beforeEach(function () {
    $this->withoutVite();
});

it('shows the owner their ITP as text', function () {
    $user = User::factory()->create();
    $itp = Itp::factory()->for($user)->create([
        'title' => 'The studio is open',
        'description' => 'For the studio in Nelson.',
        'attunement_text' => "Beloved Ann, the studio is waiting.\n\nEvery lease you sign is a yes.",
        'breath11_lines' => [['order' => 2, 'text' => 'I breathe out the doubt.'], ['order' => 1, 'text' => 'I breathe in the clay.']],
        'quantum_whys' => ['Why is it so easy to open my studio?'],
    ]);

    $this->actingAs($user)
        ->get(route('itps.show', $itp))
        ->assertOk()
        ->assertSeeLivewire(ShowItp::class)
        ->assertSeeInOrder([
            'The studio is open',
            'For the studio in Nelson.',
            '<p>Beloved Ann, the studio is waiting.</p>',
            '<p>Every lease you sign is a yes.</p>',
            'I breathe in the clay.',
            'I breathe out the doubt.',
            'Why is it so easy to open my studio?',
        ], escape: false);
});

it('is not found for anyone but the owner', function () {
    $itp = Itp::factory()->create();

    $this->actingAs(User::factory()->create())
        ->get(route('itps.show', $itp))
        ->assertNotFound();
});
