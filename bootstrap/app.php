<?php

use App\Http\Middleware\ApplyContentBundle;
use App\Http\Middleware\PreventRequestForgeryOnWeb;
use App\Http\Middleware\RestoreDeviceSignIn;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Http\Request;
use Illuminate\Session\Middleware\StartSession;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->redirectGuestsTo(fn () => route('sign-in'));
        $middleware->redirectUsersTo(fn () => route('home'));

        $middleware->web(
            append: [RestoreDeviceSignIn::class, ApplyContentBundle::class],
            replace: [PreventRequestForgery::class => PreventRequestForgeryOnWeb::class],
        );
        $middleware->appendToPriorityList(StartSession::class, RestoreDeviceSignIn::class);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
