<?php

use App\Livewire\Prompt;
use Itp\Core\Enums\EntryKind;
use Itp\Core\Enums\EntrySource;
use Itp\Core\Enums\LivingWithType;
use Itp\Core\Models\Entry;
use Itp\Core\Models\UniversalItp;
use Itp\Core\Models\User;
use Itp\Core\Models\UserSetting;
use Livewire\Livewire;

/**
 * The prompt (brief 1 §3.1): one question, written, kept for the device's app day. The server's
 * clock is frozen at noon UTC on Monday 16 November 2026.
 */
beforeEach(function () {
    $this->withoutVite();
    $this->travelTo('2026-11-16 12:00:00');
});

/**
 * A user living with a universal ITP whose one Quantum Why is the day's Why.
 */
function promptUser(): User
{
    $itp = UniversalItp::factory()->create(['short_name' => 'The Quiet Yes', 'quantum_whys' => ['Why is the step so warm at five o’clock?']]);

    return UserSetting::factory()->livingWith($itp)->create()->user;
}

it('asks each kind’s question in a light room without the bar', function (string $kind, string $kicker, string $question, string $hint) {
    $this->actingAs(promptUser())
        ->get(route('today.prompt', $kind))
        ->assertOk()
        ->assertSeeLivewire(Prompt::class)
        ->assertSee('data-room="light" data-bar="hidden"', escape: false)
        ->assertSee('href="/" data-transition="back"', escape: false)
        ->assertSeeInOrder([$kicker, $question, $hint, 'Speak', 'soon', 'Keep']);
})->with([
    'morning' => ['morning', 'Morning practice', 'What does my ITP Self want today?', 'A sentence is enough.'],
    'step' => ['step', 'One aligned step', 'What is one step toward it today?', 'Small counts: the email, the question, the rest.'],
    'evening' => ['evening', 'Evening reflection', 'What shifted today?', 'Movement, not achievement.'],
    'evidence' => ['evidence', 'Celebrate the evidence', 'What are you proud of today?', 'Glimmers, openings, courage — anything.'],
]);

it('echoes the day’s Why under the evening question', function () {
    $this->actingAs(promptUser())
        ->get(route('today.prompt', 'evening'))
        ->assertSee('This afternoon it asked: “Why is the step so warm at five o’clock?”');
});

it('knows only the four kinds', function () {
    $this->actingAs(promptUser())
        ->get('/today/someday')
        ->assertNotFound();
});

it('sends a guest to sign in', function () {
    $this->get(route('today.prompt', 'morning'))->assertRedirect(route('sign-in'));
});

it('keeps the answer for the device’s day, then goes back to Today', function () {
    $user = promptUser();

    Livewire::actingAs($user)->test(Prompt::class, ['kind' => 'morning'])
        ->call('setClock', 7, '2026-11-17', 15)
        ->set('body', '  To move slowly today.  ')
        ->call('keep')
        ->assertHasNoErrors()
        ->assertDispatched('today-navigate', url: '/', direction: 'back');

    $entry = $user->entries()->sole();
    expect($entry->kind)->toBe(EntryKind::Morning)
        ->and($entry->for_date->toDateString())->toBe('2026-11-17')
        ->and($entry->body)->toBe('To move slowly today.')
        ->and($entry->source)->toBe(EntrySource::Written)
        ->and($entry->why)->toBeNull()
        ->and($entry->living_with_type)->toBe(LivingWithType::Universal)
        ->and($entry->living_with_id)->toBe($user->settings->living_with_id);
});

it('stores the day’s Why on an evening entry', function () {
    $user = promptUser();

    Livewire::actingAs($user)->test(Prompt::class, ['kind' => 'evening'])
        ->set('body', 'I stopped at five.')
        ->call('keep');

    expect($user->entries()->sole()->why)->toBe('Why is the step so warm at five o’clock?');
});

it('replaces the day’s entry of that kind rather than adding another', function () {
    $user = promptUser();
    $earlier = Entry::factory()->for($user)->forDate('2026-11-16')->step()->create(['body' => 'Send the email.']);
    $otherDay = Entry::factory()->for($user)->forDate('2026-11-15')->step()->create(['body' => 'Yesterday’s step.']);

    Livewire::actingAs($user)->test(Prompt::class, ['kind' => 'step'])
        ->assertSet('body', 'Send the email.')
        ->set('body', 'Ask the question.')
        ->call('keep');

    expect($user->entries()->where('kind', EntryKind::Step)->orderBy('for_date')->pluck('body', 'id')->all())->toBe([
        $otherDay->id => 'Yesterday’s step.',
        $earlier->id => 'Ask the question.',
    ]);
});

it('replaces “not tonight” when the evening is reflected after all, without showing the dash', function () {
    $user = promptUser();
    Entry::factory()->for($user)->forDate('2026-11-16')->evening()->create(['body' => '—']);

    Livewire::actingAs($user)->test(Prompt::class, ['kind' => 'evening'])
        ->assertSet('body', '')
        ->set('body', 'I stopped at five.')
        ->call('keep');

    expect($user->entries()->pluck('body')->all())->toBe(['I stopped at five.']);
});

it('rejects an empty answer', function (string $body) {
    $user = promptUser();

    Livewire::actingAs($user)->test(Prompt::class, ['kind' => 'morning'])
        ->set('body', $body)
        ->call('keep')
        ->assertHasErrors(['body' => 'required'])
        ->assertSee('A few words first. A sentence is enough.')
        ->assertNotDispatched('today-navigate');

    expect($user->entries()->count())->toBe(0);
})->with(['nothing' => '', 'only spaces' => "  \n "]);

it('counts half past midnight as the day before', function () {
    $this->travelTo('2026-11-17 00:30:00');
    $user = promptUser();

    Livewire::actingAs($user)->test(Prompt::class, ['kind' => 'evening'])
        ->set('body', 'I stopped at five.')
        ->call('keep');

    expect($user->entries()->sole()->for_date->toDateString())->toBe('2026-11-16');
});
