<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'portal.auth'  => \App\Http\Middleware\EnsurePortalAuthenticated::class,
            'portal.guest' => \App\Http\Middleware\RedirectIfPortalAuthenticated::class,
            'portal.role'  => \App\Http\Middleware\EnsureUserType::class,
        ]);
        $middleware->web(append: [
            \App\Http\Middleware\AddRequestContext::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
       $exceptions->render(function (\Illuminate\Http\Exceptions\PostTooLargeException $e, $request) {
            return back()->with('error', 'The upload is too large. The logo must be 2 MB or smaller.');
        });
    })->create();
 