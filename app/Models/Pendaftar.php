<?php

namespace App\Models;

use App\Enums\StatusPendaftarEnum;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

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
        'nomor_ba',
        'approved_at',
        'hash_ba_digital',
        'current_ba_document_id',
        'notif_sent_at',
        'ba_wa_sent_at',
    ];

    protected $casts = [
        'status' => StatusPendaftarEnum::class,
        'notif_sent_at' => 'datetime',
        'approved_at' => 'datetime',
        'ba_wa_sent_at' => 'datetime',
        'portal_token' => 'encrypted',
    ];

    protected $hidden = ['portal_token', 'portal_token_hash'];

    protected static function booted(): void
    {
        static::creating(function (Pendaftar $pendaftar): void {
            if (! is_string($pendaftar->portal_token) || $pendaftar->portal_token === '') {
                $token = Str::random(48);
                $pendaftar->portal_token = $token;
                $pendaftar->portal_token_hash = hash('sha256', $token);
            }
        });
    }

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

    /** @return HasMany<BaDocument, $this> */
    public function baDocuments(): HasMany
    {
        return $this->hasMany(BaDocument::class, 'id_pendaftar');
    }

    /** @return BelongsTo<BaDocument, $this> */
    public function currentBaDocument(): BelongsTo
    {
        return $this->belongsTo(BaDocument::class, 'current_ba_document_id');
    }

    /** @return HasMany<PendaftarAppeal, $this> */
    public function appeals(): HasMany
    {
        return $this->hasMany(PendaftarAppeal::class, 'id_pendaftar');
    }
}
