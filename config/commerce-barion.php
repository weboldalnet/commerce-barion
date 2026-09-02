<?php
/**
 * Barion fizetési provider konfiguráció.
 *
 * FONTOS: az itt szereplő értékek csak ALAPÉRTELMEZÉSEK. Az admin felületen
 * (Webshop → Barion) megadott – és titkosítva tárolt – beállítások mindig
 * erősebbek, ugyanaz a minta, mint a commerce-gls és commerce-szamlazzhu
 * csomagoknál. Így éles környezetben nem kell .env-hez nyúlni.
 */
return [
    'enabled' => env('COMMERCE_BARION_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | Barion Környezet
    |--------------------------------------------------------------------------
    |
    | Értékek: 'test' (Sandbox) vagy 'prod' (Live)
    | Minden más értéket a rendszer tesztnek tekint, hogy egy elgépelés soha ne
    | indítson véletlenül éles fizetést.
    |
    */
    'environment' => env('COMMERCE_BARION_ENVIRONMENT', 'test'),

    /*
    |--------------------------------------------------------------------------
    | Bolt azonosító (POSKey)
    |--------------------------------------------------------------------------
    |
    | A Bariontól kapott 36 karakter hosszú GUID azonosító.
    | A teszt és az éles fiókhoz külön POSKey tartozik.
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
    | Értékek: 'Immediate', 'Reservation' vagy 'DelayedCapture'
    |
    */
    'payment_type' => env('COMMERCE_BARION_PAYMENT_TYPE', 'Immediate'),

    /*
    |--------------------------------------------------------------------------
    | Finanszírozási források
    |--------------------------------------------------------------------------
    |
    | A Barion SDK FundingSourceType értékei (pontosan így írva):
    | 'All', 'Balance', 'Bankcard', 'BankTransfer', 'GooglePay', 'ApplePay'
    |
    | Az adminban vesszővel elválasztott listaként tárolódik, itt tömbként is
    | megadható – a BarionSettingsService mindkettőt elfogadja.
    |
    */
    'funding_sources' => ['All'],

    /*
    |--------------------------------------------------------------------------
    | Nyelv és Pénznem
    |--------------------------------------------------------------------------
    |
    | Csak tartalék értékek: elsősorban a rendelés nyelve és pénzneme dönt,
    | ezek akkor lépnek életbe, ha az érvénytelen vagy ismeretlen.
    |
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
    'payment_window' => env('COMMERCE_BARION_PAYMENT_WINDOW', '0.00:30:00'),

    /*
    |--------------------------------------------------------------------------
    | Payer Hint
    |--------------------------------------------------------------------------
    |
    | Ha true, a vásárló email címe átadásra kerül a Barionnak, így a Barion
    | fizetőoldalán előre kitöltve jelenik meg.
    |
    */
    'payer_hint_enabled' => env('COMMERCE_BARION_PAYER_HINT_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | Azonosítók (nem admin-szerkeszthetők)
    |--------------------------------------------------------------------------
    |
    | A provider_code a rendelésekben tárolt fizetési mód kódja – megváltoztatása
    | a már leadott rendeléseket tenné felismerhetetlenné, ezért nem kerül ki az
    | admin felületre. A megjelenő elnevezés viszont ott szerkeszthető.
    |
    */
    'provider_code' => 'barion',
    'default_payment_method_label' => 'Barion bankkártyás fizetés',

    'log_payloads' => env('COMMERCE_BARION_LOG_PAYLOADS', true),
];
