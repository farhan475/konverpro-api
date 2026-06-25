<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\InternalNotification;
use App\Models\User;
use App\Services\AuditService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    use ApiResponse;

    public function __construct(private AuditService $audit) {}

    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'unread_only' => 'nullable|boolean',
        ]);

        /** @var User $user */
        $user = $request->user();
        $query = $user->internalNotifications()->latest('created_at');

        if (($validated['unread_only'] ?? false) === true) {
            $query->whereNull('read_at');
        }

        $notifications = $query->paginate(20);

        return response()->json([
            'success' => true,
            'message' => null,
            'data' => $notifications->items(),
            'unread_count' => $user->internalNotifications()->whereNull('read_at')->count(),
            'meta' => [
                'current_page' => $notifications->currentPage(),
                'per_page' => $notifications->perPage(),
                'total' => $notifications->total(),
                'last_page' => $notifications->lastPage(),
            ],
        ]);
    }

    public function markRead(Request $request, InternalNotification $notification): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        if ($notification->id_user !== $user->id) {
            return $this->errorResponse('Notifikasi bukan milik Anda.', 403);
        }

        if ($notification->read_at === null) {
            $notification->update(['read_at' => now()]);
            $this->audit->log('notification.read', 'InternalNotification', $notification->id);
        }

        return $this->successResponse($notification);
    }

    public function markAllRead(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $updated = $user->internalNotifications()
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        $this->audit->log('notification.read_all', 'InternalNotification', null, "{$updated} notifikasi ditandai dibaca.");

        return $this->successResponse(['updated' => $updated], 'Semua notifikasi ditandai dibaca.');
    }
}
