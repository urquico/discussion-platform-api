<?php

namespace App\Http\Controllers;

use App\Models\Notification;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class NotificationController extends Controller
{
    /**
     * Get user's notifications.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $perPage = $request->get('per_page', 15);
        $type = $request->get('type');
        $unreadOnly = $request->boolean('unread_only', false);
        $readOnly = $request->boolean('read_only', false);

        $query = $user->notifications()->orderBy('updated_at', 'desc');

        if ($type) {
            $query->ofType($type);
        }

        if ($unreadOnly) {
            $query->unread();
        } elseif ($readOnly) {
            $query->read();
        }

        $notifications = $query->paginate($perPage);

        return response()->json([
            'notifications' => $notifications->items(),
            'pagination' => [
                'current_page' => $notifications->currentPage(),
                'last_page' => $notifications->lastPage(),
                'per_page' => $notifications->perPage(),
                'total' => $notifications->total(),
                'has_more' => $notifications->hasMorePages(),
            ],
            'unread_count' => $user->getUnreadNotificationsCount(),
        ]);
    }

    /**
     * Get unread notifications count.
     */
    public function unreadCount(Request $request): JsonResponse
    {
        $user = $request->user();
        
        return response()->json([
            'unread_count' => $user->getUnreadNotificationsCount(),
        ]);
    }

    /**
     * Get recent notifications.
     */
    public function recent(Request $request): JsonResponse
    {
        $user = $request->user();
        $limit = $request->get('limit', 10);

        $notifications = $user->getRecentNotifications($limit);

        return response()->json([
            'notifications' => $notifications,
            'unread_count' => $user->getUnreadNotificationsCount(),
        ]);
    }

    /**
     * Mark a notification as read.
     */
    public function markAsRead(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        
        $notification = $user->notifications()->findOrFail($id);
        
        if ($notification->isUnread()) {
            $notification->markAsRead();
        }

        return response()->json([
            'message' => 'Notification marked as read',
            'notification' => $notification,
            'unread_count' => $user->getUnreadNotificationsCount(),
        ]);
    }

    /**
     * Mark a notification as unread.
     */
    public function markAsUnread(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        
        $notification = $user->notifications()->findOrFail($id);
        
        if ($notification->isRead()) {
            $notification->markAsUnread();
        }

        return response()->json([
            'message' => 'Notification marked as unread',
            'notification' => $notification,
            'unread_count' => $user->getUnreadNotificationsCount(),
        ]);
    }

    /**
     * Mark all notifications as read.
     */
    public function markAllAsRead(Request $request): JsonResponse
    {
        $user = $request->user();
        
        $count = $user->markAllNotificationsAsRead();

        return response()->json([
            'message' => "Marked {$count} notifications as read",
            'unread_count' => 0,
        ]);
    }

    /**
     * Delete a notification.
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        
        $notification = $user->notifications()->findOrFail($id);
        $notification->delete();

        return response()->json([
            'message' => 'Notification deleted',
            'unread_count' => $user->getUnreadNotificationsCount(),
        ]);
    }

    /**
     * Delete all notifications.
     */
    public function destroyAll(Request $request): JsonResponse
    {
        $user = $request->user();
        
        $count = $user->notifications()->count();
        $user->notifications()->delete();

        return response()->json([
            'message' => "Deleted {$count} notifications",
            'unread_count' => 0,
        ]);
    }

    /**
     * Get notification statistics.
     */
    public function stats(Request $request): JsonResponse
    {
        $user = $request->user();
        
        $stats = [
            'total' => $user->notifications()->count(),
            'unread' => $user->notifications()->unread()->count(),
            'read' => $user->notifications()->read()->count(),
            'by_type' => $user->notifications()
                ->selectRaw('type, COUNT(*) as count')
                ->groupBy('type')
                ->pluck('count', 'type')
                ->toArray(),
        ];

        return response()->json($stats);
    }
}
