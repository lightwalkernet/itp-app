<?php

namespace App\Sync;

use App\Support\Runtime;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Lightwalker\Sync\Client\SyncClient;
use Lightwalker\Sync\Exceptions\SyncFailed;
use Lightwalker\Sync\SyncRegistry;

/**
 * Brings server changes to universal ITPs and wildcards to a signed-out phone, on every load
 * (content bundle contract). It is the sync package's own client, limited to the `global` types
 * and pulling from the public `content/pull`, so pages, cursors and tombstones are applied
 * exactly as a signed-in sync applies them. Signed in, SyncNow covers these types. A failed
 * pull is logged and otherwise ignored, so an offline phone keeps what it has.
 */
class GuestContentPull
{
    public function __construct(protected GuestContentEndpoint $endpoint) {}

    /**
     * @return bool whether a pull completed
     */
    public function run(): bool
    {
        if (Runtime::isServer() || Auth::check()) {
            return false;
        }

        // The signed-in sync's lock: never two runs writing the same cursors at once.
        $lock = Cache::lock('sync:run', (int) config('sync.client.lock_seconds'));

        if (! $lock->get()) {
            return false;
        }

        try {
            $this->client()->pull(forceGlobal: true);

            return true;
        } catch (SyncFailed $exception) {
            Log::warning('Content pull failed: '.$exception->getMessage(), ['status' => $exception->status]);

            return false;
        } finally {
            $lock->release();
        }
    }

    protected function client(): SyncClient
    {
        $globalTypes = array_filter(
            (array) config('sync.models'),
            fn (array $definition): bool => ($definition['scope'] ?? null) === 'global',
        );

        return new SyncClient(new SyncRegistry($globalTypes), new ContentPullTransport($this->endpoint));
    }
}
