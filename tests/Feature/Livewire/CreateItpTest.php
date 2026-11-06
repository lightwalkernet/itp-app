<?php

use App\Auth\TokenStore;
use App\Livewire\CreateItp;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Itp\Core\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    Http::preventStrayRequests();
    $this->withoutVite();
});

/**
 * @return array<string, string>
 */
function requiredIntake(): array
{
    return [
        'display_name' => 'Ann',
        'address_style' => 'beloved_one',
        'q1_dedication' => 'Opening my pottery studio in Nelson by March',
        'q2_challenge' => 'I keep putting off the lease',
        'q3_old_story' => 'Artists don\'t make money',
        'q4_vision' => 'Students at the wheel on a Saturday morning',
        'q5_soulful' => 'Making things with my hands is how I pray',
    ];
}

it('starts a generation and opens it', function () {
    $user = User::factory()->create(['first_name' => 'Ann']);
    app(TokenStore::class)->put('1|secret');
    Http::fake(['api.itp.test/v1/generations' => generationResponse(['id' => '01jc0000000000000000000003', 'status' => 'queued'], 201)]);

    Livewire::actingAs($user)
        ->test(CreateItp::class)
        ->assertSet('display_name', 'Ann')
        ->fill(requiredIntake())
        ->set('q3_phrase', '  Starving artist  ')
        ->call('submit')
        ->assertHasNoErrors()
        ->assertRedirect(route('generations.show', '01jc0000000000000000000003'));

    $expected = requiredIntake() + ['q3_phrase' => 'Starving artist', 'q3_protective' => null, 'q4_scene' => null, 'avoid' => null];
    Http::assertSent(function (Request $request) use ($expected): bool {
        $sent = $request->data();
        ksort($sent);
        ksort($expected);

        return $request->method() === 'POST' && $sent === $expected;
    });
});

it('checks the required answers before calling the API', function () {
    Livewire::actingAs(User::factory()->create())
        ->test(CreateItp::class)
        ->set('display_name', '')
        ->set('address_style', 'darling')
        ->call('submit')
        ->assertHasErrors([
            'display_name' => 'required',
            'address_style' => 'in',
            'q1_dedication' => 'required',
            'q2_challenge' => 'required',
            'q3_old_story' => 'required',
            'q4_vision' => 'required',
            'q5_soulful' => 'required',
        ])
        ->assertHasNoErrors(['q3_phrase', 'q3_protective', 'q4_scene', 'avoid'])
        ->assertSee('The dedication field is required.');

    Http::assertNothingSent();
});

it('shows the API\'s errors on its fields', function () {
    app(TokenStore::class)->put('1|secret');
    Http::fake(['api.itp.test/v1/generations' => Http::response(['message' => 'Invalid.', 'data' => (object) [], 'errors' => [
        'q2_challenge' => ['Tell us a little more about the challenge.'],
    ]], 422)]);

    Livewire::actingAs(User::factory()->create())
        ->test(CreateItp::class)
        ->fill(requiredIntake())
        ->call('submit')
        ->assertHasErrors('q2_challenge')
        ->assertSee('Tell us a little more about the challenge.')
        ->assertNoRedirect();
});

it('says creating an ITP needs a connection when the phone is offline', function () {
    useMobileBuild();
    Http::fake(['api.itp.test/v1/generations' => fn () => throw new ConnectionException('Offline')]);

    Livewire::actingAs(User::factory()->create())
        ->test(CreateItp::class)
        ->fill(requiredIntake())
        ->call('submit')
        ->assertSee('Creating an ITP needs a connection.')
        ->assertSet('q1_dedication', requiredIntake()['q1_dedication'])
        ->assertNoRedirect();
});
