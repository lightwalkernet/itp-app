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

it('shows the owner their ITP', function () {
    $user = User::factory()->create();
    $itp = Itp::factory()->for($user)->create([
        'title' => 'The studio is open',
        'description' => 'For the studio in Nelson.',
        'attunement_text' => "Beloved Ann, the studio is waiting.\n\nEvery lease you sign is a yes.",
        'attunement_audio_path' => 'itps/01jc/attunement.m4a',
        'breath11_lines' => [['order' => 2, 'text' => 'I breathe out the doubt.'], ['order' => 1, 'text' => 'I breathe in the clay.']],
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
            '<audio controls preload="none" src="https://media.itp.test/itps/01jc/attunement.m4a"',
            '<p class="whitespace-pre-line o">Beloved Ann, the studio is waiting.</p>',
            '<p class="whitespace-pre-line">Every lease you sign is a yes.</p>',
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

it('lives with a personal ITP instead, then says so', function () {
    $user = User::factory()->create();
    UserSetting::factory()->for($user)->livingWith(UniversalItp::factory()->create())->create();
    $itp = Itp::factory()->for($user)->create();

    Livewire::actingAs($user)
        ->test(ShowItp::class, ['itp' => $itp])
        ->assertSee('Live with this one')
        ->call('liveWithThis')
        ->assertSee('You’re living with this one.')
        ->assertDontSee('Live with this one');

    expect($user->settings()->sole())
        ->living_with_type->toBe(LivingWithType::Itp)
        ->living_with_id->toBe($itp->id);
});
