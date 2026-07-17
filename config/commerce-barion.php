<?php
/**
 * Barion fizetési provider konfiguráció.
 */
return [
    'enabled' => env('COMMERCE_BARION_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | Barion Környezet
    |--------------------------------------------------------------------------
    |
    | Értékek: 'test' (Sandbox) vagy 'prod' (Live)
    |
    */
    'environment' => env('COMMERCE_BARION_ENVIRONMENT', 'test'),

    /*
    |--------------------------------------------------------------------------
    | Bolt azonosító (POSKey)
    |--------------------------------------------------------------------------
    |
    | A Bariontól kapott 36 karakter hosszú GUID azonosító.
    |
    */
    'pos_key' => env('COMMERCE_BARION_POS_KEY', ''),

    /*
    |--------------------------------------------------------------------------
    | Kedvezményezett (Payee)
    |--------------------------------------------------------------------------
    |
    | A Barionnál regisztrált email cím, amelyre a kifizetések érkeznek.
    |
    */
    'payee' => env('COMMERCE_BARION_PAYEE', ''),

    /*
    |--------------------------------------------------------------------------
    | Fizetés típusa
    |--------------------------------------------------------------------------
    |
    | Értékek: 'Immediate' vagy 'Reservation'
    | Alapértelmezett: 'Immediate'
    |
    */
    'payment_type' => env('COMMERCE_BARION_PAYMENT_TYPE', 'Immediate'),

    /*
    |--------------------------------------------------------------------------
    | Finanszírozási források
    |--------------------------------------------------------------------------
    |
    | Értékek tömbje: 'All', 'Balance', 'BankCard', 'GooglePay', 'ApplePay'
    | Alapértelmezett: ['All']
    |
    */
    'funding_sources' => ['All'],

    /*
    |--------------------------------------------------------------------------
    | Vendég fizetés (Guest Checkout)
    |--------------------------------------------------------------------------
    |
    | Ha true, a vásárló Barion regisztráció nélkül is fizethet bankkártyával.
    | Megjegyzés: v3 API-ban ez a mező megszűnt, de kompatibilitás miatt maradhat.
    |
    */
    'guest_checkout_enabled' => env('COMMERCE_BARION_GUEST_CHECKOUT', true),

    /*
    |--------------------------------------------------------------------------
    | Nyelv és Pénznem
    |--------------------------------------------------------------------------
    */
    'locale' => env('COMMERCE_BARION_LOCALE', 'hu-HU'),
    'currency' => env('COMMERCE_BARION_CURRENCY', 'HUF'),

    /*
    |--------------------------------------------------------------------------
    | URL-ek
    |--------------------------------------------------------------------------
    */
    'callback_url' => env('COMMERCE_BARION_CALLBACK_URL', null), // Ha null, a csomag generálja
    'redirect_url' => env('COMMERCE_BARION_REDIRECT_URL', null), // Ha null, a csomag generálja

    /*
    |--------------------------------------------------------------------------
    | Fizetési ablak érvényessége
    |--------------------------------------------------------------------------
    |
    | Formátum: [nap].[óra]:[perc]:[másodperc] (pl. "0.00:30:00" = 30 perc)
    |
    */
    'payment_window' => '0.00:30:00',

    /*
    |--------------------------------------------------------------------------
    | Payer Hint
    |--------------------------------------------------------------------------
    |
    | Ha true, a vásárló email címe átadásra kerül a Barionnak.
    |
    */
    'payer_hint_enabled' => env('COMMERCE_BARION_PAYER_HINT_ENABLED', true),

    'provider_code' => 'barion',
    'default_payment_method_label' => 'Barion bankkártyás fizetés',

    'log_payloads' => env('COMMERCE_BARION_LOG_PAYLOADS', true),
];
