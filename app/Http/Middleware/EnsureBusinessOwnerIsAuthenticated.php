<?php

namespace App\Http\Middleware;

use Illuminate\Auth\Middleware\Authenticate as Middleware;
use Illuminate\Http\Request;

/**
 * Guards every Business-portal route — mirrors App\Http\Middleware\Authenticate
 * exactly, one guard down (the 'business' guard instead of 'web'). Kept as
 * a separate class rather than reusing Authenticate directly because
 * redirectTo() must point at the Business login form, not the admin
 * Dashboard's /login.
 */
class EnsureBusinessOwnerIsAuthenticated extends Middleware
{
    protected function redirectTo(Request $request): ?string
    {
        return $request->expectsJson() ? null : route('nexus.business.login');
    }
}
