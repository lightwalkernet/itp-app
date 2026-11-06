<?php

namespace App\Http\Middleware;

use App\Auth\AppSession;
use App\Support\Runtime;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Keeps the phone signed in until the user signs out: when the session has no user (a fresh
 * launch, an expired session), sign in again from the keychain. Runs before `auth` and `guest`.
 * The web build signs in through its session only.
 */
class RestoreDeviceSignIn
{
    public function __construct(protected AppSession $session) {}

    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (Runtime::isMobile() && Auth::guest()) {
            $this->session->restore();
        }

        return $next($request);
    }
}
