<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Sends a signed-in user with no first name to the profile screen, on every visit
 * (platform.md §5: first name is collected only if empty, checked on every boot). Guests pass:
 * screens open to them (Home) must not send them anywhere.
 */
class EnsureProfileIsComplete
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user !== null && $user->first_name === null) {
            return redirect()->route('profile');
        }

        return $next($request);
    }
}
