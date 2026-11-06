<?php

namespace App\Auth;

use App\Api\AuthClient;
use App\Api\SignIn;
use App\Support\Runtime;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Itp\Core\Models\User;
use Lightwalker\Sync\Client\SyncClient;
use LogicException;

/**
 * Turns an API sign-in into a signed-in app, and ends it again (platform.md §5).
 *
 * The web build shares itp-server's database, so the API has already written the user row.
 * The phone has its own SQLite file, so the row the API returns is written there first. Both
 * then sign into their own session against that row and keep the token for later API calls.
 * The phone also keeps the user's id in the keychain, so it stays signed in until sign-out.
 */
class AppSession
{
    /**
     * The user columns the API returns that the device keeps.
     */
    protected const USER_COLUMNS = ['id', 'email', 'first_name', 'email_verified_at', 'created_at', 'updated_at'];

    public function __construct(
        protected AuthClient $auth,
        protected TokenStore $tokens,
        protected DeviceAccount $device,
    ) {}

    /**
     * @throws LogicException when the API's user isn't in the shared database
     */
    public function start(SignIn $signIn): User
    {
        $user = $this->storeUser($signIn->user);

        Auth::login($user);
        session()->regenerate();

        $this->tokens->put($signIn->token);

        if (Runtime::isMobile()) {
            $this->device->remember($user->id);
        }

        return $user;
    }

    /**
     * Sign the phone back in from the keychain. Clears the keychain when its user is no longer
     * on the phone, e.g. after the app was deleted and reinstalled (iOS keeps keychain items).
     */
    public function restore(): bool
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
        }
    }
}
