<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    use HasFactory;

    protected $fillable = [
        'key',
        'value',
        'group',
        'is_encrypted',
    ];

    protected $casts = [
        'is_encrypted' => 'boolean',
    ];

    /**
     * Obtener valor desencriptado si corresponde
     */
    public function getDecryptedValueAttribute(): ?string
    {
        if (!$this->value) {
            return null;
        }

        return $this->is_encrypted ? decrypt($this->value) : $this->value;
    }

    /**
     * Guardar y encriptar si corresponde
     */
    public static function set(string $key, ?string $value, string $group = 'general', bool $encrypt = false): void
    {
        $setting = self::firstOrNew(['key' => $key]);
        $setting->value = $encrypt && $value ? encrypt($value) : $value;
        $setting->group = $group;
        $setting->is_encrypted = $encrypt;
        $setting->save();
    }

    /**
     * Obtener valor desencriptado
     */
    public static function get(string $key, $default = null)
    {
        $setting = self::where('key', $key)->first();
        
        if (!$setting) {
            return $default;
        }

        return $setting->decrypted_value ?? $default;
    }
}
