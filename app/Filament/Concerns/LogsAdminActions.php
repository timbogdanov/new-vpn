<?php

namespace App\Filament\Concerns;

use App\Models\AdminAuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

trait LogsAdminActions
{
    protected function logAdminAction(string $action, ?Model $target = null, array $payload = []): void
    {
        AdminAuditLog::create([
            'admin_user_id' => Auth::id(),
            'action' => $action,
            'target_type' => $target ? get_class($target) : null,
            'target_id' => $target?->getKey(),
            'payload' => $payload,
        ]);
    }
}
