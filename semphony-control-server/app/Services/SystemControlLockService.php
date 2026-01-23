<?php

namespace App\Services;

use App\Models\System;
use Illuminate\Support\Facades\DB;

class SystemControlLockService
{
    public function attemptAcquireOrRefresh(int $systemId, int $userId): bool
    {
        return DB::transaction(function () use ($systemId, $userId): bool {
            $system = System::query()
                ->lockForUpdate()
                ->findOrFail($systemId);

            $isLockActive = $system->control_locked_by_user_id !== null
                && $system->control_locked_until !== null
                && $system->control_locked_until->isFuture();

            if ($isLockActive && $system->control_locked_by_user_id !== $userId) {
                return false;
            }

            $system->forceFill([
                'control_locked_by_user_id' => $userId,
                'control_locked_until' => now()->addDay(),
            ])->save();

            return true;
        }, 5);
    }

    public function release(int $systemId, int $userId): bool
    {
        return DB::transaction(function () use ($systemId, $userId): bool {
            $system = System::query()
                ->lockForUpdate()
                ->findOrFail($systemId);

            if ($system->control_locked_by_user_id !== $userId) {
                return false;
            }

            $system->forceFill([
                'control_locked_by_user_id' => null,
                'control_locked_until' => null,
            ])->save();

            return true;
        }, 5);
    }
}

