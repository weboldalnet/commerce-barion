<?php

namespace Weboldalnet\CommerceBarion\Services;

use Barion\BarionClient;
use Barion\Enumerations\BarionEnvironment;

class BarionClientFactory
{
    /**
     * Létrehoz egy felkonfigurált BarionClient példányt.
     */
    public static function create(): BarionClient
    {
        $posKey = config('commerce-barion.pos_key');
        $environment = config('commerce-barion.environment') === 'prod' 
            ? BarionEnvironment::Prod 
            : BarionEnvironment::Test;
        
        // A v3 SDK-ban az API verzió metódusonként változhat, 
        // a konstruktorban az alapértelmezett (általában v2) verziót adjuk meg.
        return new BarionClient($posKey, 2, $environment);
    }
}
