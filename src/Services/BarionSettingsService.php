<?php

namespace Weboldalnet\CommerceBarion\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Weboldalnet\CommerceBarion\Models\BarionSetting;

/**
 * Barion beállítások: az adatbázisban tárolt (adminból szerkesztett) érték az
 * elsődleges, hiányában a config/.env alapértelmezés érvényes.
 *
 * Ugyanaz a minta, mint a commerce-gls és commerce-szamlazzhu csomagoknál.
 */
class BarionSettingsService
{
    protected static $cacheKey = 'commerce_barion_settings';
    protected static $typeCacheKey = 'commerce_barion_setting_types';

    /**
     * A lapos beállítás-kulcsok leképezése a config útvonalakra.
     * A Barion configja lapos, ezért ez jelenleg azonosság – de a térkép
     * megléte teszi lehetővé, hogy később beágyazott kulcs is bekerüljön.
     */
    protected const CONFIG_PATH_MAP = [
        // A pénztárban megjelenő elnevezés – a config kulcsa hosszabb nevű.
        'payment_method_label' => 'default_payment_method_label',
        'pos_key' => 'pos_key',
        'payee' => 'payee',
        'payment_type' => 'payment_type',
        'payment_window' => 'payment_window',
        'funding_sources' => 'funding_sources',
        'locale' => 'locale',
        'currency' => 'currency',
        'callback_url' => 'callback_url',
        'redirect_url' => 'redirect_url',
        'payer_hint_enabled' => 'payer_hint_enabled',
        'log_payloads' => 'log_payloads',
    ];

    /**
     * Az admin beállítófelületen szerkeszthető kulcsok.
     * Ezekre a DB érték hiányában is a tényleges (config/.env) értéket mutatjuk.
     */
    public static function viewKeys(): array
    {
        return [
            'enabled', 'environment', 'payment_method_label',
            'pos_key', 'payee',
            'payment_type', 'payment_window', 'funding_sources',
            'locale', 'currency',
            'callback_url', 'redirect_url',
            'payer_hint_enabled', 'log_payloads',
        ];
    }

    /** Titkosítva tárolandó kulcsok */
    public static function encryptedKeys(): array
    {
        return ['pos_key'];
    }

    /** Logikai (checkbox) kulcsok */
    public static function booleanKeys(): array
    {
        return ['enabled', 'payer_hint_enabled', 'log_payloads'];
    }

    public static function all(): array
    {
        try {
            return Cache::rememberForever(self::$cacheKey, function () {
                return BarionSetting::all()->pluck('value', 'key')->toArray();
            });
        } catch (\Throwable $e) {
            // A tábla még nem létezik (migráció előtt) – a config érvényes.
            return [];
        }
    }

    /**
     * Kulcs => típus térkép egyetlen lekérdezésből, cache-elve.
     */
    protected static function types(): array
    {
        try {
            return Cache::rememberForever(self::$typeCacheKey, function () {
                return BarionSetting::all()->pluck('type', 'key')->toArray();
            });
        } catch (\Throwable $e) {
            return [];
        }
    }

    protected static function configDefault($key, $default = null)
    {
        $path = self::CONFIG_PATH_MAP[$key] ?? $key;
        $value = config('commerce-barion.' . $path);

        if ($value !== null && $value !== '') {
            return $value;
        }

        return $default;
    }

    public static function get($key, $default = null)
    {
        $settings = self::all();
        $hasDbValue = array_key_exists($key, $settings) && $settings[$key] !== null && $settings[$key] !== '';
        $value = $hasDbValue ? $settings[$key] : self::configDefault($key, $default);

        $type = self::types()[$key] ?? null;

        // A titkosítás csak a DB-ben tárolt értékre vonatkozik, a config/.env értéke nyers.
        if ($hasDbValue && $type === 'encrypted' && $value) {
            try {
                return Crypt::decryptString($value);
            } catch (\Throwable $e) {
                return $value;
            }
        }

        if ($type === 'boolean') {
            return filter_var($value, FILTER_VALIDATE_BOOLEAN);
        }

        return $value;
    }

    public static function getBool($key, $default = false): bool
    {
        return filter_var(self::get($key, $default), FILTER_VALIDATE_BOOLEAN);
    }

    public static function save($key, $value, $type = 'string', $group = 'general'): void
    {
        if ($type === 'encrypted' && $value) {
            $value = Crypt::encryptString($value);
        }

        BarionSetting::updateOrCreate(
            ['key' => $key],
            ['value' => $value, 'type' => $type, 'group' => $group]
        );

        self::clearCache();
    }

    /**
     * Van-e elegendő adat a Barion hívásokhoz?
     */
    public static function hasCredentials(): bool
    {
        return (string) self::get('pos_key') !== '' && (string) self::get('payee') !== '';
    }

    /**
     * 'test' vagy 'prod' – minden más értéket tesztnek tekintünk, hogy egy
     * elgépelés soha ne indítson véletlenül éles fizetést.
     */
    public static function environment(): string
    {
        return self::get('environment', 'test') === 'prod' ? 'prod' : 'test';
    }

    public static function isProd(): bool
    {
        return self::environment() === 'prod';
    }

    /**
     * A finanszírozási források normalizált tömbje.
     *
     * Az adminban vesszővel elválasztott listaként tároljuk, a configban viszont
     * tömb is lehet – mindkettőt elfogadjuk.
     */
    public static function fundingSources(): array
    {
        $value = self::get('funding_sources', ['All']);

        if (is_string($value)) {
            $value = array_map('trim', explode(',', $value));
        }

        $value = array_values(array_filter((array) $value, function ($item) {
            return $item !== '' && $item !== null;
        }));

        return !empty($value) ? $value : ['All'];
    }

    public static function clearCache(): void
    {
        Cache::forget(self::$cacheKey);
        Cache::forget(self::$typeCacheKey);
    }
}
