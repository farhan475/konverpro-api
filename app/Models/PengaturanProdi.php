<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PengaturanProdi extends Model
{
    protected $table = 'pengaturan_prodi';
    protected $primaryKey = 'id_prodi';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id_prodi',
        'min_nilai_huruf',
        'max_konversi_sks_persen',
        'format_no_ba',
        'metode_pengakuan',
    ];

    /** @return BelongsTo<Prodi, $this> */
    public function prodi(): BelongsTo
    {
        return $this->belongsTo(Prodi::class, 'id_prodi');
    }
}
