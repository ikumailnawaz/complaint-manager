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
            // If the requested URI is outside the tracking module, relock immediately!
            if (!$request->is('tracking*') && !$request->is('api/v1/tracking*')) {
                session()->forget('tracking_module_unlocked');
            }
        }

        return $next($request);
    }
}
