<?php

use App\Api\AuthClient;
use App\Api\Exceptions\ApiUnavailable;
use App\Api\Exceptions\AppUpdateRequired;
use App\Api\Exceptions\SignedOut;
use App\Auth\TokenStore;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;

/**
 * itp-server's response shape: `{message, data, errors}`, all three keys always present.
 *
 * @param  array<string, mixed>  $data
 * @param  array<string, list<string>>  $errors
 */
function apiResponse(string $message, array $data = [], array $errors = [], int $status = 200): mixed
{
    return Http::response(['message' => $message, 'data' => (object) $data, 'errors' => (object) $errors], $status);
}

beforeEach(function () {
    Http::preventStrayRequests();
});

it('sends the app version and asks for JSON on every call', function () {
    Http::fake(['api.itp.test/v1/auth/send-code' => apiResponse('We sent a sign-in code to that email.')]);

    app(AuthClient::class)->sendCode('ann@example.com');

    Http::assertSent(fn (Request $request) => $request->url() === 'https://api.itp.test/v1/auth/send-code'
        && $request->method() === 'POST'
        && $request['email'] === 'ann@example.com'
        && $request->header('X-App-Version') === ['1.4.2']
        && $request->header('Accept') === ['application/json']);
});

it('returns the signed-in user, token and profile state', function () {
    Http::fake(['api.itp.test/v1/auth/verify-code' => apiResponse('Signed in.', [
        'user' => ['id' => '01JC0000000000000000000000', 'email' => 'ann@example.com', 'first_name' => null],
        'token' => '1|secret',
        'needs_profile' => true,
    ])]);

    $signIn = app(AuthClient::class)->verifyCode('ann@example.com', '123456', 'Web: test');

    expect($signIn->user['id'])->toBe('01JC0000000000000000000000')
        ->and($signIn->token)->toBe('1|secret')
        ->and($signIn->needsProfile)->toBeTrue();
    Http::assertSent(fn (Request $request) => $request['device_name'] === 'Web: test' && $request['code'] === '123456');
});

it('turns API validation errors into field errors', function () {
    Http::fake(['api.itp.test/v1/auth/verify-code' => apiResponse('That code is incorrect.', errors: [
        'code' => ['That code is incorrect.'],
    ], status: 422)]);

    expect(fn () => app(AuthClient::class)->verifyCode('ann@example.com', '000000', 'Web: test'))
        ->toThrow(fn (ValidationException $exception) => expect($exception->errors())->toBe(['code' => ['That code is incorrect.']]));
});

it('puts a rate limit on the form field it belongs to', function () {
    Http::fake(['api.itp.test/v1/auth/send-code' => apiResponse('Too Many Attempts.', status: 429)]);

    expect(fn () => app(AuthClient::class)->sendCode('ann@example.com'))
        ->toThrow(fn (ValidationException $exception) => expect($exception->errors())->toBe(['email' => ['Too Many Attempts.']]));
});

it('reports a required update with the minimum version', function () {
    Http::fake(['api.itp.test/v1/*' => apiResponse('This version of the app needs an update.', ['min_app_version' => '2.0.0'], [
        'app_version' => ['Version 1.4.2 is no longer supported.'],
    ], 426)]);

    expect(fn () => app(AuthClient::class)->sendCode('ann@example.com'))
        ->toThrow(fn (AppUpdateRequired $exception) => expect($exception->minimumVersion)->toBe('2.0.0'));
});

it('reports being signed out on a 401', function () {
    app(TokenStore::class)->put('1|revoked');
    Http::fake(['api.itp.test/v1/auth/complete-profile' => apiResponse('Unauthenticated.', status: 401)]);

    app(AuthClient::class)->completeProfile('Ann');
})->throws(SignedOut::class);

it('reports being signed out without calling the API when there is no token', function () {
    app(AuthClient::class)->completeProfile('Ann');

    Http::assertNothingSent();
})->throws(SignedOut::class);

it('sends the stored token on authenticated calls', function () {
    app(TokenStore::class)->put('1|secret');
    Http::fake(['api.itp.test/v1/auth/complete-profile' => apiResponse('Profile updated.', [
        'user' => ['id' => '01JC0000000000000000000000', 'first_name' => 'Ann'],
        'needs_profile' => false,
    ])]);

    $user = app(AuthClient::class)->completeProfile('Ann');

    expect($user['first_name'])->toBe('Ann');
    Http::assertSent(fn (Request $request) => $request->header('Authorization') === ['Bearer 1|secret']);
});

it('reports an unreachable or failing API', function (Closure $response) {
    Http::fake(['api.itp.test/v1/*' => $response]);

    app(AuthClient::class)->sendCode('ann@example.com');
})->with([
    'connection refused' => fn () => fn () => throw new ConnectionException('Connection refused'),
    'server error' => fn () => fn () => apiResponse('Server Error', status: 500),
])->throws(ApiUnavailable::class);

it('never throws when revoking the token fails', function () {
    app(TokenStore::class)->put('1|secret');
    Http::fake(['api.itp.test/v1/auth/logout' => fn () => throw new ConnectionException('Offline')]);

    expect(fn () => app(AuthClient::class)->logout())->not->toThrow(Throwable::class);
});

it('revokes the stored token', function () {
    app(TokenStore::class)->put('1|secret');
    Http::fake(['api.itp.test/v1/auth/logout' => apiResponse('Signed out.')]);

    app(AuthClient::class)->logout();

    Http::assertSent(fn (Request $request) => $request->url() === 'https://api.itp.test/v1/auth/logout'
        && $request->header('Authorization') === ['Bearer 1|secret']);
});
