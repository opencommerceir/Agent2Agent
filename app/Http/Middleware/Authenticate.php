<?php

namespace App\Http\Middleware;

use Illuminate\Auth\Middleware\Authenticate as Middleware;
use Illuminate\Http\Request;

/**
 * Guards every `/dashboard/*` route — an unauthenticated request is
 * redirected to the login form rather than getting Laravel's default JSON
 * 401 (this app's only JSON API is the separate MCP Gateway, which has its
 * own, unrelated Agent-bearer-token auth and never touches this guard at
 * all).
 */
class Authenticate extends Middleware
{
    protected function redirectTo(Request $request): ?string
    {
        return $request->expectsJson() ? null : route('login');
    }
}
