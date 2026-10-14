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
            // Ignore background AJAX, JSON API, notifications polling, and asset requests
            if ($request->ajax() 
                || $request->wantsJson() 
                || $request->header('X-Requested-With') === 'XMLHttpRequest'
                || $request->is('notifications*', 'storage*', 'assets*', 'build*', 'favicon.ico')
            ) {
                return $next($request);
            }

            // Only clear lock when performing full-page GET navigation outside the tracking module
            if ($request->isMethod('GET') && !$request->is('tracking*') && !$request->is('api/v1/tracking*')) {
                session()->forget('tracking_module_unlocked');
            }
        }

        return $next($request);
    }
}
