<?php

namespace App\Http\Middleware;

use App\Content\ContentBundle;
use App\Support\Runtime;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Applies the bundled universal ITPs and wildcards on the phone before any screen reads them:
 * after an install or an app update, the first request writes the new bundle; every other
 * request costs one query and a stat. A broken bundle is reported, never shown to the user.
 */
class ApplyContentBundle
{
    public function __construct(protected ContentBundle $bundle) {}

    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (Runtime::isMobile()) {
            try {
                $this->bundle->applyIfChanged();
            } catch (Throwable $exception) {
                report($exception);
            }
        }

        return $next($request);
    }
}
