<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Keeps an already-logged-in Business owner off the login/register forms
 * — mirrors App\Http\Middleware\RedirectIfAuthenticated exactly, one
 * guard down (redirects to the Business dashboard instead of the admin
 * Dashboard).
 */
class RedirectIfBusinessOwnerIsAuthenticated
{
    public function handle(Request $request, Closure $next, string ...$guards): Response
    {
        $guards = empty($guards) ? ['business'] : $guards;

        foreach ($guards as $guard) {
            if (Auth::guard($guard)->check()) {
                return redirect(route('nexus.business.dashboard'));
            }
        }

        return $next($request);
    }
}
