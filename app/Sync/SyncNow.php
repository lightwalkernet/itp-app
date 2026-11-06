<?php

namespace App\Sync;

use App\Auth\TokenStore;
use App\Support\Runtime;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Lightwalker\Sync\Client\SyncClient;
use Lightwalker\Sync\Client\SyncReport;
use Lightwalker\Sync\Exceptions\SyncFailed;

/**
 * One sync run from a screen, on the phone only: the web build reads the shared database and
 * never runs the client (platform.md §7). A failed run is logged and otherwise ignored, so an
 * offline phone keeps showing what it already has; the next run picks up where this one stopped.
 */
class SyncNow
{
    public function __construct(protected TokenStore $tokens) {}

    /**
     * @return SyncReport|null null when nothing ran: the web build, signed out, another run in
     *                         progress, or the run failed
     */
    public function run(): ?SyncReport
    {
        if (Runtime::isServer() || Auth::guest() || $this->tokens->get() === null) {
            return null;
        }

        try {
            return app(SyncClient::class)->run();
        } catch (SyncFailed $exception) {
            Log::warning('Sync failed: '.$exception->getMessage(), ['status' => $exception->status]);

            return null;
        }
    }
}
