<?php

namespace App\Api;

use App\Api\Exceptions\ApiUnavailable;
use App\Api\Exceptions\AppUpdateRequired;
use App\Api\Exceptions\SignedOut;
use App\Auth\TokenStore;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;

/**
 * Calls itp-server's API (`api.`, version 1), the same way on both builds. Every call sends
 * X-App-Version, and every answer comes back in the API's one shape:
 * `{"message": "...", "data": {...}, "errors": {...}}`.
 */
abstract class ApiClient
{
    public function __construct(protected TokenStore $tokens) {}

    /**
     * Send a request and return the response's `data`.
     *
     * @param  array<string, mixed>  $payload
     * @param  string  $errorField  where a message without field errors (e.g. a rate limit) is shown
     * @return array<string, mixed>
     *
     * @throws ValidationException for a 422 or 429
     * @throws AppUpdateRequired for a 426
     * @throws SignedOut for a 401
     * @throws ApiUnavailable when the API can't be reached or fails
     */
    protected function send(string $method, string $uri, array $payload = [], bool $authenticated = false, string $errorField = 'form'): array
    {
        try {
            $response = $this->request($authenticated)->send($method, $uri, ['json' => $payload]);
        } catch (ConnectionException $exception) {
            throw new ApiUnavailable($exception);
        }

        return match (true) {
            $response->successful() => (array) $response->json('data', []),
            $response->status() === 426 => throw new AppUpdateRequired($response->json('data.min_app_version')),
            $response->status() === 401 => throw new SignedOut,
            in_array($response->status(), [422, 429], true) => throw $this->validationException($response, $errorField),
            default => throw new ApiUnavailable,
        };
    }

    protected function request(bool $authenticated): PendingRequest
    {
        $request = Http::baseUrl(rtrim(config('itp.api_base_url'), '/').'/v1/')
            ->acceptJson()
            ->withHeaders(['X-App-Version' => config('itp.version')])
            ->timeout(15);

        if ($authenticated) {
            $request->withToken($this->tokens->get() ?? throw new SignedOut);
        }

        return $request;
    }

    protected function validationException(Response $response, string $errorField): ValidationException
    {
        $errors = (array) $response->json('errors', []);

        return ValidationException::withMessages(
            $errors !== [] ? $errors : [$errorField => $response->json('message', 'Something went wrong.')],
        );
    }
}
