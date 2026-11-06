<?php

namespace App\Http\Middleware;

use App\Support\Runtime;
use Closure;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * CSRF protection for the web build only. On the phone every request comes from the app's own
 * web view to the PHP runtime inside it, with no server another site could post to, so there is
 * nothing to protect. Skipping it also means Livewire never shows "page expired" on the phone.
 */
class PreventRequestForgeryOnWeb extends PreventRequestForgery
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle($request, Closure $next)
    {
        if (Runtime::isMobile()) {
            return $next($request);
        }

        return parent::handle($request, $next);
    }
}
