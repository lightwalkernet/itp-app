<?php

namespace App\Sync;

use App\Auth\TokenStore;
use Lightwalker\Sync\Client\ServerEndpoint;

/**
 * Where the phone's sync client sends push and pull: itp-server's `v1` API, as the signed-in
 * user, with the same X-App-Version header as every other API call (platform.md §4, §7). Read
 * on every request, never cached: the phone keeps one app booted across requests.
 */
class ApiServerEndpoint implements ServerEndpoint
{
    public function __construct(protected TokenStore $tokens) {}

    public function baseUrl(): string
    {
        return rtrim(config('itp.api_base_url'), '/').'/v1';
    }

    public function token(): ?string
    {
        return $this->tokens->get();
    }

    /**
     * @return array<string, string>
     */
    public function headers(): array
    {
        return ['X-App-Version' => (string) config('itp.version')];
    }
}
