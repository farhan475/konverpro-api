<?php

namespace App\Services;

use App\Models\InternalNotification;
use App\Models\User;
use Illuminate\Support\Collection;

class InternalNotificationService
{
    public function notifyUser(
        string $userId,
        string $type,
        string $title,
        string $message,
        ?string $actionUrl = null,
        ?string $subjectType = null,
        ?string $subjectId = null
    ): InternalNotification {
        return InternalNotification::create([
            'id_user' => $userId,
            'type' => $type,
            'title' => $title,
            'message' => $message,
            'action_url' => $actionUrl,
            'subject_type' => $subjectType,
            'subject_id' => $subjectId,
            'created_at' => now(),
        ]);
    }

    public function notifyRole(
        string $role,
        string $type,
        string $title,
        string $message,
        ?string $actionUrl = null,
        ?string $subjectType = null,
        ?string $subjectId = null
    ): void {
        $rawUserIds = User::query()
            ->where('role', $role)
            ->where('status', 'active')
            ->pluck('id');
        $userIds = $rawUserIds
            ->filter(fn ($id): bool => is_string($id))
            ->map(fn ($id): string => (string) $id)
            ->values();

        $this->notifyUsers($userIds, $type, $title, $message, $actionUrl, $subjectType, $subjectId);
    }

    /**
     * @param  Collection<int, string>  $userIds
     */
    public function notifyUsers(
        Collection $userIds,
        string $type,
        string $title,
        string $message,
        ?string $actionUrl = null,
        ?string $subjectType = null,
        ?string $subjectId = null
    ): void {
        foreach ($userIds->unique() as $userId) {
            $this->notifyUser($userId, $type, $title, $message, $actionUrl, $subjectType, $subjectId);
        }
    }
}
