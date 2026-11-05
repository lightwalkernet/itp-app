<?php

namespace App\Providers;

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

        // The mobile build's SecureStorage store is bound here once NativePHP is installed.
        $this->app->bind(TokenStore::class, SessionTokenStore::class);
    }

    /**
     * Bootstrap any application services.
     *
     * The web build runs no migrations: it uses itp-server's MySQL database as it is
     * (platform.md §4). Tests build that schema in SQLite from itp-core's shared tables and
     * the sync counter. The mobile build's migrations are loaded with NativePHP.
     */
    public function boot(): void
    {
        if ($this->app->runningUnitTests()) {
            $this->loadMigrationsFrom([
                ItpCoreServiceProvider::migrationsPath(),
                SyncServiceProvider::serverMigrationsPath(),
            ]);

            return;
        }

        Runtime::assertMatchesDatabase(DB::connection()->getDriverName());
    }
}
