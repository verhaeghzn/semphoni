<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequireTwoFactorAuthentication
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Only enforce 2FA in production
        if (app()->environment('production')) {
            $user = $request->user();

            // Check if user is authenticated and doesn't have 2FA enabled
            if ($user && ! $user->hasEnabledTwoFactorAuthentication()) {
                // Allow access to 2FA settings page and login/logout routes
                if (! $request->routeIs('two-factor.show') 
                    && ! $request->routeIs('login')
                    && ! $request->routeIs('logout')) {
                    return redirect()->route('two-factor.show');
                }
            }
        }

        return $next($request);
    }
}
