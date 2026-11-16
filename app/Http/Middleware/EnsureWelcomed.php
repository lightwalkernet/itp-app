<?php

namespace App\Http\Middleware;

use App\Support\Welcome;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Sends a guest who hasn't been welcomed on this device to the welcome first (player contract).
 * Never a signed-in user. Without an Introduction in the content there is nothing to welcome
 * with, so the welcome counts as seen and Today opens.
 */
class EnsureWelcomed
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user() !== null || Welcome::seen()) {
            return $next($request);
        }

        if (Welcome::introduction() === null) {
            Welcome::markSeen();

            return $next($request);
        }

        return redirect()->route('welcome');
    }
}
