<?php

namespace App\Http\Middleware;

use App\Auth\AppSession;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * When the session has no user (a fresh launch, an expired session), sign in again from the
 * phone's keychain or a trusted browser's cookie. Runs before `auth` and `guest`.
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
        if (Auth::guest()) {
            $this->session->restore();
        }

        return $next($request);
    }
}
