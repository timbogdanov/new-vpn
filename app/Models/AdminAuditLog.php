<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AdminAuditLog extends Model
{
    protected $table = 'admin_audit_log';

    protected $fillable = [
        'admin_user_id',
        'action',
        'target_type',
        'target_id',
        'payload',
    ];

    protected function casts(): array
    {
        return ['payload' => 'array'];
    }

    public function admin(): BelongsTo
    {
        return $this->belongsTo(User::class, 'admin_user_id');
    }
}
