<?php

use App\Support\Runtime;

it('is the server build unless the flag says mobile', function (?string $flag, bool $isMobile) {
    config(['itp.runtime' => $flag]);

    expect(Runtime::isMobile())->toBe($isMobile)
        ->and(Runtime::isServer())->toBe(! $isMobile)
        ->and(Runtime::syncRole())->toBe($isMobile ? 'client' : 'server');
})->with([
    'server' => ['server', false],
    'mobile' => ['mobile', true],
    'missing' => [null, false],
    'empty' => ['', false],
    'misspelt' => ['Mobile', false],
]);

it('sets the sync role from the flag', function () {
    expect(config('sync.role'))->toBe('server');
});

it('accepts the database that matches the build', function (string $flag, string $driver) {
    config(['itp.runtime' => $flag]);

    Runtime::assertMatchesDatabase($driver);

    expect(true)->toBeTrue();
})->with([
    'web on MySQL' => ['server', 'mysql'],
    'phone on SQLite' => ['mobile', 'sqlite'],
]);

it('refuses a database that does not match the build', function (string $flag, string $driver, string $message) {
    config(['itp.runtime' => $flag]);

    expect(fn () => Runtime::assertMatchesDatabase($driver))->toThrow(LogicException::class, $message);
})->with([
    'web on SQLite' => ['server', 'sqlite', 'ITP_RUNTIME is "server", so the default database must be mysql, not sqlite.'],
    'phone on MySQL' => ['mobile', 'mysql', 'ITP_RUNTIME is "mobile", so the default database must be sqlite, not mysql.'],
]);

it('marks the document as native only on the mobile build', function (string $flag, bool $isNative) {
    config(['itp.runtime' => $flag]);
    $this->withoutVite();

    $html = $this->get(route('sign-in'))->assertOk()->getContent();

    expect(str_contains($html, '<html lang="en" class="is-native">'))->toBe($isNative);
})->with([
    'web' => ['server', false],
    'phone' => ['mobile', true],
]);
