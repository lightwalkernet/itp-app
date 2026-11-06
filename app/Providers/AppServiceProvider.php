<?php

namespace App\Providers;

use App\Auth\SecureStorageTokenStore;
use App\Auth\SessionTokenStore;
use App\Auth\TokenStore;
use App\Support\Runtime;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\ServiceProvider;
use Itp\Core\ItpCoreServiceProvider;
use Lightwalker\Sync\SyncServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        config(['sync.role' => Runtime::syncRole()]);

        $this->app->bind(TokenStore::class, fn ($app): TokenStore => Runtime::isMobile()
            ? $app->make(SecureStorageTokenStore::class)
            : $app->make(SessionTokenStore::class));
    }

    /**
     * Bootstrap any application services.
     *
     * The web build runs no migrations: it uses itp-server's MySQL database as it is
     * (platform.md §4). The mobile build builds its own SQLite file from itp-core's shared
     * tables and the sync client's cursors; NativePHP runs `migrate` on the device after each
     * install. Tests build both schemas in one SQLite database, so either build can be tested.
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
        }

        Runtime::assertMatchesDatabase(DB::connection()->getDriverName());
    }
}
