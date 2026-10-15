<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;

/**
 * Einfacher Key/Value-Speicher. Werte werden verschluesselt abgelegt.
 */
class Setting extends Model
{
    protected $primaryKey = 'key';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = [];

    public static function get(string $key, mixed $default = null): mixed
    {
        $row = static::find($key);
        if ($row === null || $row->value === null) {
            return $default;
        }

        return json_decode(Crypt::decryptString($row->value), true);
    }

    public static function put(string $key, mixed $value): void
    {
        static::updateOrCreate(['key' => $key], [
            'value' => $value === null ? null : Crypt::encryptString(json_encode($value)),
        ]);
    }

    public static function forget(string $key): void
    {
        static::whereKey($key)->delete();
    }
}
