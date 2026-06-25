<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BaDocument extends Model
{
    use HasUuids;

    protected $table = 'ba_documents';

    protected $fillable = [
        'id_pendaftar',
        'version',
        'document_number',
        'document_hash',
        'status',
        'approved_by',
        'approved_at',
        'revoked_at',
        'revoked_reason',
        'replaced_by_id',
    ];

    protected $casts = [
        'approved_at' => 'datetime',
        'revoked_at' => 'datetime',
    ];

    /** @return BelongsTo<Pendaftar, $this> */
    public function pendaftar(): BelongsTo
    {
        return $this->belongsTo(Pendaftar::class, 'id_pendaftar');
    }
}
