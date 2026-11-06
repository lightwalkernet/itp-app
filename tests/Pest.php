<?php

use GuzzleHttp\Promise\PromiseInterface;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Native\Mobile\SecureStorage;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->use(LazilyRefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

function something()
{
    // ..
}

/**
 * itp-server's answer carrying one Generation object (step 6 contract). Summary keys default.
 *
 * @param  array<string, mixed>  $generation
 */
function generationResponse(array $generation, int $status = 200): PromiseInterface
{
    return Http::response(['message' => 'OK', 'errors' => (object) [], 'data' => ['generation' => $generation + [
        'id' => '01jc0000000000000000000001',
        'created_at' => '2026-11-07T09:00:00.000000Z',
        'updated_at' => '2026-11-07T09:05:00.000000Z',
        'itp_id' => null,
    ]]], $status);
}

/**
 * Switch to the mobile build, with an in-memory keychain holding a signed-in token.
 */
function useMobileBuild(): SecureStorage
{
    config(['itp.runtime' => 'mobile', 'sync.role' => 'client']);

    $keychain = new class extends SecureStorage
    {
        /** @var array<string, string> */
        public array $items = ['itp.api_token' => '1|secret'];

        public function set(string $key, ?string $value, $accessibility = null): bool
        {
            $this->items[$key] = $value;

            return true;
        }

        public function get(string $key): ?string
        {
            return $this->items[$key] ?? null;
        }

        public function delete(string $key): bool
        {
            unset($this->items[$key]);

            return true;
        }
    };

    app()->instance(SecureStorage::class, $keychain);

    return $keychain;
}

/**
 * The sync server's pull answer: the given records for one type, nothing for the others.
 *
 * @param  array<string, list<array<string, mixed>>>  $recordsByType
 */
function syncPullResponder(array $recordsByType): Closure
{
    return function (Request $request) use ($recordsByType): PromiseInterface {
        parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);
        $records = (int) $query['after'] === 0 ? ($recordsByType[$query['type']] ?? []) : [];

        return Http::response(['message' => 'OK', 'errors' => (object) [], 'data' => [
            'records' => $records,
            'next_cursor' => $records === [] ? (int) $query['after'] : (int) end($records)['sync_seq'],
            'has_more' => false,
        ]]);
    };
}
