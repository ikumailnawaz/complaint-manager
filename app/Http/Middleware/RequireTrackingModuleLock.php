<?php

namespace App\Http\Middleware;

use App\Models\Tracking\TrackingSetting;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequireTrackingModuleLock
{
    /**
     * Protects the Tracking & Monitoring module behind an admin password challenge.
     * When any other module is navigated to, the session lock is cleared automatically.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = auth()->user();
        if (!$user) {
            return redirect()->route('login');
        }

        // Check if unlocked in current session
        if (!session('tracking_module_unlocked', false)) {
            if ($request->ajax() || $request->wantsJson()) {
                return response()->json([
                    'success' => false,
                    'locked'  => true,
                    'message' => 'Tracking module is locked. Password verification required.',
                ], 423);
            }

            return redirect()->route('tracking.lock', ['redirect' => $request->fullUrl()]);
        }

        return $next($request);
    }
}
