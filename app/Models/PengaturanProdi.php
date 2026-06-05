<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PengaturanProdi extends Model
{
    protected $table = 'pengaturan_prodi';

    protected $primaryKey = 'id_prodi';

    public $incrementing = false;

    public $timestamps = false;

    protected $fillable = [
        'id_prodi',
        'min_akreditasi_asal',
        'max_usia_ijazah_tahun',
        'max_konversi_sks_persen',
        'min_nilai_huruf',
        'min_ipk',
        'format_no_ba',
        'metode_pengakuan',
    ];

    protected $casts = [
        'max_usia_ijazah_tahun' => 'integer',
        'max_konversi_sks_persen' => 'integer',
        'min_ipk' => 'decimal:2',
    ];

    /** @return BelongsTo<Prodi, $this> */
    public function prodi(): BelongsTo
    {
        return $this->belongsTo(Prodi::class, 'id_prodi');
    }
}
