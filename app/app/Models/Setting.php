<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;

class Setting extends Model
{
    protected $primaryKey = 'key';
    public $incrementing = false;
    protected $keyType = 'string';
    protected $fillable = ['key', 'value', 'is_encrypted'];
    protected $casts = ['is_encrypted' => 'boolean'];

    public static function get(string $key, mixed $default = null): mixed
    {
        return Cache::remember("setting:$key", 60, function () use ($key, $default) {
            $row = static::find($key);
            if (!$row) return $default;
            $value = $row->is_encrypted ? Crypt::decryptString($row->value) : $row->value;
            return $value === null ? $default : $value;
        });
    }

    public static function put(string $key, mixed $value, bool $encrypt = false): void
    {
        $storedValue = $encrypt ? Crypt::encryptString((string) $value) : (string) $value;
        static::updateOrCreate(
            ['key' => $key],
            ['value' => $storedValue, 'is_encrypted' => $encrypt]
        );
        Cache::forget("setting:$key");
    }
}
