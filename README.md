# Barion fizetési provider a commerce-core-hoz

Ez a csomag lehetővé teszi a Barion fizetési kapu használatát a `weboldalnet/commerce-core` alapú rendszerekben.

## Telepítés

1. Másold be a csomagot a `vendor/weboldalnet/commerce-barion` mappába.
2. Futtasd a `composer require barion/barion-web-php:^3.0` parancsot a projekt gyökerében.
3. Regisztráld a Service Providert a `config/app.php` fájlban (Laravel auto-discovery esetén nem szükséges):
   `Weboldalnet\CommerceBarion\CommerceBarionServiceProvider::class`

## Konfiguráció

Publikáld a konfigurációs fájlt:
```bash
php artisan vendor:publish --tag=commerce-barion-config
```

Állítsd be az `.env` fájlban a szükséges adatokat:
```env
COMMERCE_BARION_ENABLED=true
COMMERCE_BARION_ENVIRONMENT=test
COMMERCE_BARION_POS_KEY=your-pos-key-guid
COMMERCE_BARION_PAYEE=your-barion-email@example.com
COMMERCE_BARION_CURRENCY=HUF
COMMERCE_BARION_LOCALE=hu-HU
```

## Használat a Webshopban

A `webshop-ai-default` csomag automatikusan felismeri a Bariont, ha:
1. A `commerce-barion` csomag telepítve és engedélyezve van.
2. A webshop beállításokban az **Online fizetés** engedélyezve van.
3. A checkout mód **Rendelés leadása**.

## Webhook és Visszatérés

A csomag automatikusan regisztrálja az alábbi útvonalakat:
- `/commerce/barion/return` (Visszatérő oldal)
- `/commerce/barion/callback` (Szerver-szerver callback / IPN)

## Biztonság

A csomag a Barion v3 SDK-t használja, és minden fizetési állapotot közvetlenül a Barion API-tól kérdez le a callback feldolgozásakor. A tranzakciók idempotensek a `commerce-core` PaymentCallbackProcessor-on keresztül.
Érzékeny adatok (POSKey) nem kerülnek logolásra.
