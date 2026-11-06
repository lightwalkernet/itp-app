<?php

namespace App\Providers;

use App\Auth\SecureStorageTokenStore;
use App\Auth\SessionTokenStore;
use App\Auth\TokenStore;
use App\Support\Runtime;
use App\Sync\ApiServerEndpoint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\ServiceProvider;
use Itp\Core\ItpCoreServiceProvider;
use Lightwalker\Sync\Client\ServerEndpoint;
use Lightwalker\Sync\SyncServiceProvider;
use Native\Mobile\Runtime as NativeRuntime;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * The sync client talks to itp-server's API. Only the phone runs it (App\Sync\SyncNow).
     */
    public function register(): void
    {
        config(['sync.role' => Runtime::syncRole()]);

        $this->app->bind(TokenStore::class, fn ($app): TokenStore => Runtime::isMobile()
            ? $app->make(SecureStorageTokenStore::class)
            : $app->make(SessionTokenStore::class));

        $this->app->bind(ServerEndpoint::class, ApiServerEndpoint::class);
    }

    /**
     * Bootstrap any application services.
     *
     * The web build runs no migrations: it uses itp-server's MySQL database as it is
     * (platform.md §4). The mobile build builds its own SQLite file from itp-core's shared
     * tables and the sync client's cursors; NativePHP runs `migrate` on the device after each
     * install. NativePHP keeps one app booted across requests on the phone ("persistent"
     * mode) and doesn't reset auth, so the signed-in user is forgotten after each request and
     * loaded fresh from the session on the next. Tests build both schemas in one SQLite
     * database, so either build can be tested.
     */
    public function boot(): void
    {
        if ($this->app->runningUnitTests()) {
            $this->loadMigrationsFrom([
                ItpCoreServiceProvider::migrationsPath(),
                SyncServiceProvider::serverMigrationsPath(),
                SyncServiceProvider::clientMigrationsPath(),
            ]);

            return;
        }

        if (Runtime::isMobile()) {
            $this->loadMigrationsFrom([
                ItpCoreServiceProvider::migrationsPath(),
                SyncServiceProvider::clientMigrationsPath(),
            ]);

            NativeRuntime::onReset(fn ($app) => $app['auth']->forgetGuards());
        }

        Runtime::assertMatchesDatabase(DB::connection()->getDriverName());
    }
}
