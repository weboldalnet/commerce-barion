<?php

namespace Weboldalnet\CommerceBarion\Services;

use Barion\BarionClient;
use Barion\Enumerations\BarionEnvironment;

class BarionClientFactory
{
    /**
     * Létrehoz egy felkonfigurált BarionClient példányt.
     *
     * A POSKey és a környezet az admin beállításokból jön (Webshop → Barion);
     * ha ott nincs megadva, a config/.env érvényes.
     */
    public static function create(): BarionClient
    {
        $posKey = (string) BarionSettingsService::get('pos_key');

        $environment = BarionSettingsService::isProd()
            ? BarionEnvironment::Prod
            : BarionEnvironment::Test;

        // A v3 SDK-ban az API verzió metódusonként változhat,
        // a konstruktorban az alapértelmezett (általában v2) verziót adjuk meg.
        return new BarionClient($posKey, 2, $environment);
    }
}
