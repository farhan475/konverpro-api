<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Contracts\Encryption\DecryptException;

class PengaturanGlobal extends Model
{
    protected $table      = 'pengaturan_global';
    protected $primaryKey = 'setting_key';
    protected $keyType    = 'string';
    public $incrementing  = false;
    public const CREATED_AT = null;

    protected $fillable = ['setting_key', 'setting_value'];

    /**
     * Key-key ini disimpan terenkripsi di database.
     * Enkripsi/dekripsi terjadi otomatis via get() dan set().
     */
    private const ENCRYPTED_KEYS = [
        'sumopod_api_key',
        'smtp_password',
        'fonnte_api_key',
    ];

    /**
     * Ambil nilai setting. Key sensitif otomatis didekripsi.
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        $record = static::find($key);
        if (!$record || $record->setting_value === null || $record->setting_value === '') {
            return $default;
        }

        if (in_array($key, self::ENCRYPTED_KEYS)) {
            try {
                return Crypt::decryptString($record->setting_value);
            } catch (DecryptException) {
                // Nilai belum terenkripsi (misal dari seeder lama) — kembalikan apa adanya
                return $record->setting_value;
            }
        }

        return $record->setting_value;
    }

    /**
     * Simpan nilai setting. Key sensitif otomatis dienkripsi.
     */
    public static function set(string $key, mixed $value): void
    {
        $stored = $value;

        if (in_array($key, self::ENCRYPTED_KEYS) && !empty($value)) {
            $stored = Crypt::encryptString((string) $value);
        }

        static::updateOrCreate(
            ['setting_key' => $key],
            ['setting_value' => $stored]
        );
    }

    public static function isEncrypted(string $key): bool
    {
        return in_array($key, self::ENCRYPTED_KEYS);
    }
}
