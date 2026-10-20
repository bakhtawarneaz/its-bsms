<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserType
{
    /**
     * Allow the request only for the listed user types, e.g. portal.role:superadmin,admin
     */
    public function handle(Request $request, Closure $next, string ...$types): Response
    {
        $userType = strtolower((string) $request->session()->get('auth_user.user_type'));

        abort_unless(in_array($userType, $types, true), 403);

        return $next($request);
    }
}