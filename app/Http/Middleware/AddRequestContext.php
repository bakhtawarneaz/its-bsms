<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class AddRequestContext
{
    /**
     * Attach request metadata to every log line written during this request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        Context::add('request_id', (string) Str::uuid());
        Context::add('ip', $request->ip());

        $username = $request->session()->get('auth_user.username')
            ?? $request->session()->get('otp_user.username');

        if ($username) {
            Context::add('user', $username);
        }

        return $next($request);
    }
}