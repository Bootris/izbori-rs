<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class Setting extends Model
{
    /** Optional outbound links shown on the public "Informacije" page (key => admin label). */
    public const INFO_LINKS = [
        'legislation_url' => 'Zakoni i propisi',
        'observers_url' => 'Posmatrači (domaći i međunarodni)',
        'nominators_url' => 'Informacije za podnosioce lista',
        'forms_url' => 'Obrasci',
        'commission_url' => 'Republička izborna komisija',
        'news_url' => 'Saopštenja i vesti',
    ];

    protected $fillable = ['key', 'value'];

    public static function get(string $key, mixed $default = null): mixed
    {
        return static::allCached()[$key] ?? $default;
    }

    public static function set(string $key, mixed $value): void
    {
        static::updateOrCreate(['key' => $key], ['value' => $value]);
        Cache::forget('site_settings');
    }

    /** @return array<string, mixed> */
    public static function allCached(): array
    {
        return Cache::remember('site_settings', 3600, fn () => static::query()->pluck('value', 'key')->all());
    }
}
