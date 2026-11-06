<?php

use App\Api\Exceptions\ApiUnavailable;
use App\Api\Exceptions\NotFound;
use App\Api\Exceptions\SignedOut;
use App\Api\GenerationClient;
use App\Auth\TokenStore;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    Http::preventStrayRequests();
    app(TokenStore::class)->put('1|secret');
});

it('calls each generation endpoint as the signed-in user', function (Closure $call, string $method, string $url, array $body) {
    Http::fake(['api.itp.test/v1/generations*' => generationResponse(['status' => 'assessing'])]);

    $generation = $call(app(GenerationClient::class));

    expect($generation['status'])->toBe('assessing');
    Http::assertSent(fn (Request $request) => $request->method() === $method
        && $request->url() === $url
        && $request->data() === $body
        && $request->header('Authorization') === ['Bearer 1|secret']
        && $request->header('X-App-Version') === ['1.4.2']
        && $request->header('Accept') === ['application/json']);
})->with([
    'create' => [
        fn (GenerationClient $client) => $client->create(['display_name' => 'Ann', 'avoid' => null]),
        'POST', 'https://api.itp.test/v1/generations', ['display_name' => 'Ann', 'avoid' => null],
    ],
    'find' => [
        fn (GenerationClient $client) => $client->find('01jc0000000000000000000001'),
        'GET', 'https://api.itp.test/v1/generations/01jc0000000000000000000001', [],
    ],
    'answer follow-ups' => [
        fn (GenerationClient $client) => $client->answerFollowups('01jc0000000000000000000001', [['followup_id' => 12, 'answer_text' => 'Yes']]),
        'POST', 'https://api.itp.test/v1/generations/01jc0000000000000000000001/followups', ['answers' => [['followup_id' => 12, 'answer_text' => 'Yes']]],
    ],
    'flag a segment' => [
        fn (GenerationClient $client) => $client->flagSegment('01jc0000000000000000000001', 40, 'Too loud', null),
        'POST', 'https://api.itp.test/v1/generations/01jc0000000000000000000001/segments/40/flags', ['user_explanation' => 'Too loud', 'requested_change' => null],
    ],
    'accept' => [
        fn (GenerationClient $client) => $client->accept('01jc0000000000000000000001'),
        'POST', 'https://api.itp.test/v1/generations/01jc0000000000000000000001/accept', [],
    ],
]);

it('lists the user\'s generations', function () {
    Http::fake(['api.itp.test/v1/generations' => Http::response(['message' => 'OK', 'errors' => (object) [], 'data' => ['generations' => [
        ['id' => '01jc0000000000000000000002', 'status' => 'awaiting_followup', 'created_at' => '2026-11-07T09:00:00Z', 'updated_at' => '2026-11-07T09:00:00Z', 'itp_id' => null],
    ]]])]);

    expect(array_column(app(GenerationClient::class)->index(), 'id'))->toBe(['01jc0000000000000000000002']);
});

it('puts the API\'s validation errors on the intake fields', function () {
    Http::fake(['api.itp.test/v1/generations' => Http::response(['message' => 'Invalid.', 'data' => (object) [], 'errors' => [
        'q1_dedication' => ['The q1 dedication field is required.'],
    ]], 422)]);

    expect(fn () => app(GenerationClient::class)->create(['display_name' => 'Ann']))
        ->toThrow(fn (ValidationException $exception) => expect($exception->errors())->toBe(['q1_dedication' => ['The q1 dedication field is required.']]));
});

it('reports someone else\'s generation as not found', function () {
    Http::fake(['api.itp.test/v1/generations/*' => Http::response(['message' => 'Not found.', 'data' => (object) [], 'errors' => (object) []], 404)]);

    app(GenerationClient::class)->find('01jc0000000000000000000009');
})->throws(NotFound::class);

it('reports an unreachable API', function () {
    Http::fake(['api.itp.test/v1/generations' => fn () => throw new ConnectionException('Offline')]);

    app(GenerationClient::class)->index();
})->throws(ApiUnavailable::class);

it('reports being signed out without calling the API when there is no token', function () {
    app(TokenStore::class)->forget();

    app(GenerationClient::class)->index();

    Http::assertNothingSent();
})->throws(SignedOut::class);
