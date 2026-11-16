<?php

use App\Livewire\ShowItp;
use Itp\Core\Enums\LivingWithType;
use Itp\Core\Models\Itp;
use Itp\Core\Models\UniversalItp;
use Itp\Core\Models\User;
use Itp\Core\Models\UserSetting;
use Livewire\Livewire;

beforeEach(function () {
    $this->withoutVite();
    config(['itp.media_url' => 'https://media.itp.test']);
});

it('shows the owner their ITP by its title, with only its Quantum Whys to play until it has a voice', function () {
    $user = User::factory()->create();
    $itp = Itp::factory()->for($user)->create([
        'title' => 'The studio is open',
        'description' => 'For the studio in Nelson.',
        'attunement_text' => 'Beloved Ann, the studio is waiting.',
        'attunement_audio_path' => null,
        'breath11_lines' => [['order' => 1, 'text' => 'I breathe in the clay.']],
        'quantum_whys' => ['Why is it so easy to open my studio?'],
        'created_at' => '2026-11-12 09:00:00',
    ]);

    $this->actingAs($user)
        ->get(route('itps.show', $itp))
        ->assertOk()
        ->assertSeeLivewire(ShowItp::class)
        ->assertSee('data-room="white"', escape: false)
        ->assertSeeInOrder([
            'href="/itps" data-transition="back"',
            'Made from your words · 12 November',
            '<h1 class="t-room itp-title itp-title--personal">The studio is open</h1>',
            'For the studio in Nelson.',
            'wire:click="makeThisMyItp"',
            'disabled="disabled" data-piece="itp"', '<small>no voice yet</small>',
            'disabled="disabled" data-piece="breathe"', '<small>no voice yet</small>',
            '<a href="/play/itp/'.$itp->id.'/why" data-transition="forward"',
        ], escape: false)
        ->assertDontSee('Beloved Ann')
        ->assertDontSee('I breathe in the clay.')
        ->assertDontSee('Why is it so easy to open my studio?')
        ->assertDontSee('class="fold"', escape: false);
});

it('plays a personal ITP that has its voice', function () {
    $user = User::factory()->create();
    $itp = Itp::factory()->for($user)->create(['attunement_audio_path' => 'itps/01jc/attunement.m4a']);

    $this->actingAs($user)
        ->get(route('itps.show', $itp))
        ->assertOk()
        ->assertSee('<a href="/play/itp/'.$itp->id.'/itp" data-transition="forward"', escape: false);
});

it('is not found for anyone but the owner', function () {
    $itp = Itp::factory()->create();

    $this->actingAs(User::factory()->create())
        ->get(route('itps.show', $itp))
        ->assertNotFound();
});

it('makes a personal ITP the user\'s, then says so', function () {
    $user = User::factory()->create();
    UserSetting::factory()->for($user)->livingWith(UniversalItp::factory()->create())->create();
    $itp = Itp::factory()->for($user)->create();

    Livewire::actingAs($user)
        ->test(ShowItp::class, ['itp' => $itp])
        ->assertSee('Make this my ITP')
        ->call('makeThisMyItp')
        ->assertSee('This is your ITP.')
        ->assertDontSee('Make this my ITP');

    expect($user->settings()->sole())
        ->living_with_type->toBe(LivingWithType::Itp)
        ->living_with_id->toBe($itp->id);
});
