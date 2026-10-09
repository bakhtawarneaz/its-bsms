<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RedirectIfPortalAuthenticated
{
    /**
     * Send already logged-in users away from guest-only pages such as login.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->session()->has('auth_user')) {
            return redirect()->route('dashboard');
        }

        return $next($request);
    }
}