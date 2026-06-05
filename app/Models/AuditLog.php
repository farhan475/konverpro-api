<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuditLog extends Model
{
    protected $table = 'audit_logs';

    public const UPDATED_AT = null;

    protected $fillable = [
        'id_user',
        'id_kampus',
        'action',
        'details',
        'ip_address',
    ];

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'id_user');
    }

    /** @return BelongsTo<Kampus, $this> */
    public function kampus(): BelongsTo
    {
        return $this->belongsTo(Kampus::class, 'id_kampus');
    }
}
