<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class Setting extends Model
{
    protected $table = 'settings';

    protected $primaryKey = 'key';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = [];

    public static function get(string $key, mixed $default = null): mixed
    {
        $all = Cache::remember('pay.settings', 300, fn () => static::query()->pluck('value', 'key')->all());

        return $all[$key] ?? $default;
    }

    public static function put(string $key, ?string $value, ?int $userId = null): void
    {
        static::updateOrCreate(['key' => $key], ['value' => $value, 'updated_by' => $userId]);
        Cache::forget('pay.settings');
    }
}
