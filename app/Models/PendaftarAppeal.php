<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PendaftarAppeal extends Model
{
    use HasUuids;

    protected $fillable = [
        'id_pendaftar',
        'reason',
        'additional_information',
        'status',
        'resolution_notes',
        'resolved_by',
        'resolved_at',
    ];

    protected $casts = ['resolved_at' => 'datetime'];

    /** @return BelongsTo<Pendaftar, $this> */
    public function pendaftar(): BelongsTo
    {
        return $this->belongsTo(Pendaftar::class, 'id_pendaftar');
    }
}
