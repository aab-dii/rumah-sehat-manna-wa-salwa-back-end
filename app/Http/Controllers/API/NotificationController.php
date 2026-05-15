<?php

namespace App\Http\Controllers\API;

use App\Helpers\ResponseFormatter;
use App\Http\Controllers\Controller;
use App\Models\Notification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Events\NotificationCountUpdated;

class NotificationController extends Controller
{
    /**
     * GET /notifications
     * Returns paginated list of notifications for the authenticated user,
     * filtered by user_id AND for_role so no cross-role leakage occurs.
     */
    public function index(Request $request)
    {
        $user = Auth::user();

        $query = Notification::where('user_id', $user->id)
            ->where('for_role', $user->role) // <<< Segmentasi berdasarkan role
            ->orderBy('created_at', 'desc');

        if ($request->boolean('unread_only')) {
            $query->where('is_read', false);
        }

        $notifications = $query->paginate($request->input('limit', 15));

        return ResponseFormatter::success($notifications, 'Daftar notifikasi berhasil diambil');
    }

    /**
     * POST /notifications/{id}/read
     * Mark a single notification as read.
     */
    public function markAsRead($id)
    {
        $user = Auth::user();

        $notification = Notification::where('id', $id)
            ->where('user_id', $user->id)
            ->where('for_role', $user->role) // <<< Cegah lintas-role
            ->firstOrFail();

        $notification->update(['is_read' => true]);

        $unreadCount = Notification::where('user_id', $user->id)
            ->where('for_role', $user->role)
            ->where('is_read', false)
            ->count();

        event(new NotificationCountUpdated($user->id, $unreadCount));

        return ResponseFormatter::success($notification, 'Notifikasi ditandai sudah dibaca');
    }

    /**
     * POST /notifications/read-all
     * Mark all notifications of the authenticated user as read.
     */
    public function markAllRead()
    {
        $user = Auth::user();

        Notification::where('user_id', $user->id)
            ->where('for_role', $user->role)
            ->where('is_read', false)
            ->update(['is_read' => true]);

        event(new NotificationCountUpdated($user->id, 0));

        return ResponseFormatter::success(null, 'Semua notifikasi ditandai sudah dibaca');
    }

    /**
     * DELETE /notifications/{id}
     * Delete a single notification.
     */
    public function destroy($id)
    {
        $user = Auth::user();

        $notification = Notification::where('id', $id)
            ->where('user_id', $user->id)
            ->where('for_role', $user->role)
            ->firstOrFail();

        $notification->delete();

        $unreadCount = Notification::where('user_id', $user->id)
            ->where('for_role', $user->role)
            ->where('is_read', false)
            ->count();

        event(new NotificationCountUpdated($user->id, $unreadCount));

        return ResponseFormatter::success(null, 'Notifikasi berhasil dihapus');
    }

    /**
     * GET /notifications/unread-count
     * Return the count of unread notifications for the logged-in user's role.
     */
    public function unreadCount()
    {
        $user = Auth::user();

        $count = Notification::where('user_id', $user->id)
            ->where('for_role', $user->role)
            ->where('is_read', false)
            ->count();

        return ResponseFormatter::success(['count' => $count], 'Jumlah notifikasi belum dibaca');
    }
}
