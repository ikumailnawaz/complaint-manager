<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ClearTrackingLockOnOtherModules
{
    /**
     * If the user navigates to any route outside the tracking module,
     * immediately relock the tracking module so the password is required again.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (session('tracking_module_unlocked')) {
            $routeName = $request->route() ? $request->route()->getName() : '';
            // If the route does not start with 'tracking.', relock it!
            if ($routeName && !str_starts_with($routeName, 'tracking.')) {
                session()->forget('tracking_module_unlocked');
            }
        }

        return $next($request);
    }
}
