<?php

namespace Weboldalnet\CommerceBarion;

use Illuminate\Support\ServiceProvider;
use Weboldalnet\CommerceBarion\Support\PackageHelper;
use Weboldalnet\CommerceBarion\Console\ExtendViewsCommerceBarionsCommand;
use Weboldalnet\CommerceBarion\Console\InstallCommerceBarionsCommand;

class CommerceBarionServiceProvider extends ServiceProvider
{
    public function boot()
    {
        // route-ok
        $this->loadRoutesFrom(__DIR__.'/../routes/web.php');
        $this->loadViewsFrom(__DIR__.'/../settings/views', PackageHelper::PACKAGE_PREFIX);

        // migrációk
        //$this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        $publishList = [];
        foreach (PackageHelper::PACKAGE_LIST as $name => $publish) {
            $this->publishes([
                $publish['source'] => base_path($publish['destination']),
            ], PackageHelper::PACKAGE_PREFIX . '-' . $name);

            $publishList[$publish['source']] = base_path($publish['destination']);
        }

        $this->publishes($publishList, PackageHelper::PACKAGE_PREFIX . '-all');
    }

    public function register()
    {
    }
}
