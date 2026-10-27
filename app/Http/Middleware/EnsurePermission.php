<?php

namespace App\Http\Middleware;

use App\Support\Permissions;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Usage: ->middleware('portal.can:GWUserCreate')
 */
class EnsurePermission
{
    public function __construct(private readonly Permissions $permissions) {}

    public function handle(Request $request, Closure $next, string $flag): Response
    {
        abort_unless($this->permissions->has($flag), 403, 'You do not have permission for this action.');

        return $next($request);
    }
}