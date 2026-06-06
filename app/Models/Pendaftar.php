<?php

namespace App\Models;

use App\Enums\StatusPendaftarEnum;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Pendaftar extends Model
{
    use HasUuids;

    protected $table = 'pendaftar';

    protected $fillable = [
        'id_prodi',
        'created_by',
        'nama_lengkap',
        'nim_asal',
        'email',
        'no_whatsapp',
        'asal_kampus',
        'asal_prodi',
        'file_transkrip_excel_path',
        'file_transkrip_pdf_path',
        'status',
        'total_sks_diakui',
        'catatan_revisi',
        'hash_ba_digital',
        'notif_sent_at',
    ];

    protected $casts = [
        'status' => StatusPendaftarEnum::class,
        'notif_sent_at' => 'datetime',
    ];

    /** @return BelongsTo<Prodi, $this> */
    public function prodi(): BelongsTo
    {
        return $this->belongsTo(Prodi::class, 'id_prodi');
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return HasMany<TranskripAsal, $this> */
    public function transkripAsal(): HasMany
    {
        return $this->hasMany(TranskripAsal::class, 'id_pendaftar');
    }

    /** @return HasMany<HasilKonversi, $this> */
    public function hasilKonversi(): HasMany
    {
        return $this->hasMany(HasilKonversi::class, 'id_pendaftar');
    }
}
