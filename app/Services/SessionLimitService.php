<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;

class SessionLimitService
{
    private const MAX_ACTIVE_SESSIONS = 2;

    public function enforce(int $userId, string $currentSessionId): void
    {
        $activeSince = now()->subMinutes((int) config('session.lifetime'))->timestamp;

        DB::transaction(function () use ($userId, $currentSessionId, $activeSince): void {
            User::query()->whereKey($userId)->lockForUpdate()->firstOrFail();

            DB::table('sessions')
                ->where('user_id', $userId)
                ->where('last_activity', '<', $activeSince)
                ->delete();

            $olderSessionIds = DB::table('sessions')
                ->where('user_id', $userId)
                ->where('last_activity', '>=', $activeSince)
                ->where('id', '!=', $currentSessionId)
                ->orderBy('last_activity')
                ->orderBy('id')
                ->pluck('id');

            $sessionIdsToClose = $olderSessionIds->slice(self::MAX_ACTIVE_SESSIONS - 1);

            if ($sessionIdsToClose->isNotEmpty()) {
                DB::table('sessions')->whereIn('id', $sessionIdsToClose)->delete();
            }
        });
    }
}
