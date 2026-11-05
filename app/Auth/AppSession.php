<?php

namespace App\Auth;

use App\Api\AuthClient;
use App\Api\SignIn;
use Illuminate\Support\Facades\Auth;
use Itp\Core\Models\User;
use LogicException;

/**
 * Turns an API sign-in into a signed-in app, and ends it again (platform.md §5).
 *
 * The web build shares itp-server's database, so it signs the user into its own session
 * against the row the API just wrote, and keeps the token for later API calls.
 */
class AppSession
{
    public function __construct(
        protected AuthClient $auth,
        protected TokenStore $tokens,
    ) {}

    /**
     * @throws LogicException when the API's user isn't in the shared database
     */
    public function start(SignIn $signIn): User
    {
        $user = User::find($signIn->user['id'])
            ?? throw new LogicException("Signed in as user [{$signIn->user['id']}], but that row isn't in the database.");

        Auth::login($user);
        session()->regenerate();

        $this->tokens->put($signIn->token);

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
     * End the local session only, e.g. when the API has already said the token is gone.
     */
    public function endLocally(): void
    {
        $this->tokens->forget();

        Auth::logout();
        session()->invalidate();
        session()->regenerateToken();
    }
}
