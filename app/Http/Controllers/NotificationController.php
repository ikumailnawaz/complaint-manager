<?php

namespace App\Http\Controllers;

use App\Models\AppNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class NotificationController extends Controller
{
    /**
     * Display a full audit view of user notifications.
     */
    public function index(Request $request)
    {
        $user = Auth::user();
        $query = AppNotification::forUser($user)->latest();

        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }

        if ($request->input('status') === 'unread') {
            $query->unread();
        }

        $notifications = $query->paginate(25)->withQueryString();
        $unreadCount = AppNotification::forUser($user)->unread()->count();

        return view('notifications.index', compact('notifications', 'unreadCount'));
    }

    /**
     * API endpoint returning unread count and latest unread notifications.
     */
    public function unread(Request $request): JsonResponse
    {
        $user = Auth::user();
        if (!$user) {
            return response()->json(['count' => 0, 'notifications' => [], 'latest_id' => 0]);
        }

        $notifications = AppNotification::forUser($user)
            ->unread()
            ->latest()
            ->take(8)
            ->get()
            ->map(function ($notif) {
                return [
                    'id' => $notif->id,
                    'type' => $notif->type,
                    'title' => $notif->title,
                    'message' => $notif->message,
                    'link' => $notif->link,
                    'icon' => $notif->icon,
                    'color' => $notif->color,
                    'time_ago' => $notif->created_at->diffForHumans(null, true, true),
                    'created_at' => $notif->created_at->toIso8601String(),
                ];
            });

        $count = AppNotification::forUser($user)->unread()->count();
        $latestId = $notifications->first()['id'] ?? 0;

        return response()->json([
            'count' => $count,
            'notifications' => $notifications,
            'latest_id' => $latestId,
        ]);
    }

    /**
     * Mark a single notification as read.
     */
    public function markRead(Request $request, AppNotification $notification): JsonResponse
    {
        $notification->markAsRead();

        return response()->json([
            'success' => true,
            'remaining_unread' => AppNotification::forUser(Auth::user())->unread()->count(),
        ]);
    }

    /**
     * Mark all unread notifications as read.
     */
    public function markAllRead(Request $request): JsonResponse
    {
        $user = Auth::user();
        AppNotification::forUser($user)->unread()->update([
            'is_read' => true,
            'read_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'remaining_unread' => 0,
        ]);
    }
}
