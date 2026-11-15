<?php

namespace App\Auth;

use App\Api\AuthClient;
use App\Api\SignIn;
use App\Support\Runtime;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Itp\Core\Models\User;
use Itp\Core\Models\UserSetting;
use Lightwalker\Sync\Client\SyncClient;
use LogicException;

/**
 * Turns an API sign-in into a signed-in app, and ends it again (platform.md §5).
 *
 * The web build shares itp-server's database, so the API has already written the user row.
 * The phone has its own SQLite file, so the rows the API returns (the user, and their settings)
 * are written there first. Both
 * then sign into their own session against that row and keep the token for later API calls.
 * The phone also keeps the user's id in the keychain, so it stays signed in until sign-out. A
 * browser the user said is theirs keeps its sign-in in a cookie, for 90 days (TrustedBrowser).
 */
class AppSession
{
    /**
     * The user columns the API returns that the device keeps.
     */
    protected const USER_COLUMNS = ['id', 'email', 'first_name', 'email_verified_at', 'created_at', 'updated_at'];

    /**
     * The settings columns the API returns that the device keeps.
     */
    protected const SETTINGS_COLUMNS = [
        'id', 'user_id', 'living_with_type', 'living_with_id', 'morning_at', 'why_at', 'evening_at',
        'morning_reminder', 'why_reminder', 'evening_reminder', 'created_at', 'updated_at',
    ];

    public function __construct(
        protected AuthClient $auth,
        protected TokenStore $tokens,
        protected DeviceAccount $device,
        protected TrustedBrowser $browser,
    ) {}

    /**
     * @throws LogicException when the API's user isn't in the shared database
     */
    public function start(SignIn $signIn, bool $trustThisBrowser = false): User
    {
        $user = $this->storeUser($signIn->user);

        if (Runtime::isMobile() && $signIn->settings !== null) {
            $this->writeSettings($signIn->settings);
        }

        Auth::login($user);
        session()->regenerate();

        $this->tokens->put($signIn->token);

        if (Runtime::isMobile()) {
            $this->device->remember($user->id);
        } elseif ($trustThisBrowser) {
            $this->browser->remember($user->id, $signIn->token);
        }

        return $user;
    }

    /**
     * Sign back in when the session has no user: the phone from its keychain, a trusted browser
     * from its cookie.
     */
    public function restore(): bool
    {
        return Runtime::isMobile() ? $this->restoreFromKeychain() : $this->restoreFromTrustedBrowser();
    }

    /**
     * Clears the keychain when its user is no longer on the phone, e.g. after the app was deleted
     * and reinstalled (iOS keeps keychain items).
     */
    protected function restoreFromKeychain(): bool
    {
        $userId = $this->device->userId();

        if ($userId === null || $this->tokens->get() === null) {
            return false;
        }

        $user = User::find($userId);

        if ($user === null) {
            $this->tokens->forget();
            $this->device->forget();

            return false;
        }

        Auth::login($user);

        return true;
    }

    /**
     * Puts the token back in the session too, because the session that held it has gone.
     */
    protected function restoreFromTrustedBrowser(): bool
    {
        $trusted = $this->browser->read();

        if ($trusted === null) {
            return false;
        }

        $user = User::find($trusted['user_id']);

        if ($user === null) {
            $this->browser->forget();

            return false;
        }

        Auth::login($user);

        $this->tokens->put($trusted['token']);

        return true;
    }

    /**
     * The local copy of a user row the API returned. On the phone the row is written first,
     * through the model (platform.md §4); `users` is pull-only, so the write is never pushed.
     * The signed-in user is swapped for the fresh copy, so this request sees the change too.
     *
     * @param  array<string, mixed>  $row  the user as the API returns it
     *
     * @throws LogicException when the API's user isn't in the shared database
     */
    public function storeUser(array $row): User
    {
        $user = Runtime::isServer()
            ? User::find($row['id']) ?? throw new LogicException("Signed in as user [{$row['id']}], but that row isn't in the database.")
            : $this->writeUser($row);

        if (Auth::id() === $user->getKey()) {
            Auth::setUser($user);
        }

        return $user;
    }

    /**
     * Write the API's user row to the phone's database.
     *
     * @param  array<string, mixed>  $row
     */
    protected function writeUser(array $row): User
    {
        $user = User::withTrashed()->find($row['id']) ?? new User;

        $user->forceFill(Arr::only($row, self::USER_COLUMNS));
        $user->deleted_at = null;
        $user->timestamps = false;
        $user->save();

        return $user;
    }

    /**
     * Write the API's settings row to the phone's database, through the model (platform.md §4).
     * On the web the row is already in the shared database. It is the server's own copy, so it is
     * then marked clean, as a pull would leave it: pushing it back could overwrite a change made on
     * the web since sign-in, and a dirty row is skipped by the next pull.
     *
     * @param  array<string, mixed>  $row
     */
    protected function writeSettings(array $row): UserSetting
    {
        $settings = UserSetting::withTrashed()->find($row['id'])
            ?? UserSetting::withTrashed()->where('user_id', $row['user_id'])->first()
            ?? new UserSetting;

        $settings->forceFill(Arr::only($row, self::SETTINGS_COLUMNS));
        $settings->deleted_at = null;
        $settings->timestamps = false;
        $settings->save();

        $settings->newQueryWithoutScopes()->whereKey($settings->getKey())->update(['sync_dirty' => false]);

        return $settings->refresh();
    }

    /**
     * Revoke the token on the server, then end the local session, even if the API call fails.
     */
    public function end(): void
    {
        $this->auth->logout();

        $this->endLocally();
    }

    /**
     * End the local session only, e.g. when the API has already said the token is gone. On the
     * phone this also wipes the user's synced rows.
     */
    public function endLocally(): void
    {
        $this->tokens->forget();

        Auth::logout();
        session()->invalidate();
        session()->regenerateToken();

        if (Runtime::isMobile()) {
            $this->device->forget();

            app(SyncClient::class)->signOut();
        } else {
            $this->browser->forget();
        }
    }
}
