<?php

namespace App\Http\Middleware;

use App\Models\Tracking\TrackingDevice;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateTrackingKey
{
    /**
     * Authenticate requests using long-lived X-Tracking-Key header.
     * This allows background telemetry to sync even if the user logs out of the app.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $rawKey = $request->header('X-Tracking-Key');
        $deviceId = $request->header('X-Device-Id');

        if (!$rawKey) {
            return response()->json([
                'success' => false,
                'code'    => 'missing_tracking_key',
                'message' => 'X-Tracking-Key header is required for background telemetry.',
            ], 401);
        }

        $hash = TrackingDevice::hashKey($rawKey);
        $device = TrackingDevice::where('key_hash', $hash)->first();

        if (!$device) {
            return response()->json([
                'success' => false,
                'code'    => 'tracking_key_revoked',
                'message' => 'Tracking key is invalid or has been revoked.',
            ], 401);
        }

        if ($device->revoked_at !== null) {
            return response()->json([
                'success' => false,
                'code'    => 'tracking_key_revoked',
                'message' => 'Tracking key was revoked: ' . ($device->revoked_reason ?? 'Device retired'),
            ], 401);
        }

        // Bind user to request
        $user = $device->user;
        if (!$user) {
            return response()->json([
                'success' => false,
                'code'    => 'user_not_found',
                'message' => 'Associated engineer user account does not exist.',
            ], 401);
        }

        $device->update(['last_seen_at' => now()]);

        $request->setUserResolver(fn() => $user);
        $request->attributes->set('tracking_device', $device);

        return $next($request);
    }
}
