<?php

namespace Weboldalnet\CommerceBarion;

use Illuminate\Support\ServiceProvider;
use Weboldalnet\CommerceCore\Managers\PaymentManager;
use Weboldalnet\CommerceBarion\Providers\BarionPaymentProvider;
use Weboldalnet\CommerceBarion\Services\BarionSettingsService;
use Weboldalnet\CommerceBarion\Support\PackageHelper;
use Weboldalnet\CommerceBarion\Console\InstallCommerceBarionCommand;
use Weboldalnet\CommerceBarion\Console\ExtendViewsCommerceBarionCommand;

class CommerceBarionServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap the application services.
     */
    public function boot()
    {
        // Route-ok és admin nézetek betöltése
        $this->loadRoutesFrom(__DIR__ . '/../routes/web.php');
        $this->loadViewsFrom(__DIR__ . '/../settings/views', PackageHelper::PACKAGE_PREFIX);

        // Migrációk (a csomag maga tölti be, ahogy a commerce-core és a commerce-gls is)
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');

        // Provider regisztráció a commerce-core-ba.
        // Az admin beállítás az elsődleges, hiányában a config/.env dönt.
        try {
            $paymentManager = $this->app->make(PaymentManager::class);
            $code = config('commerce-barion.provider_code', 'barion');

            // Telepített integrációként mindig bejelentkezünk, hogy a webshop
            // beállítófelületén akkor is látszódjon (és onnan visszakapcsolható
            // legyen), ha a modul épp ki van kapcsolva.
            $paymentManager->registerAvailable($code, [
                'name' => (string) BarionSettingsService::get('payment_method_label', 'Barion bankkártyás fizetés'),
                'settings_url' => '/webshop/barion/settings',
                'settings_label' => 'Barion',
                'online' => true,
            ]);

            if (BarionSettingsService::getBool('enabled', true)) {
                $paymentManager->register($code, $this->app->make(BarionPaymentProvider::class));
            }
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('Barion regisztrációs hiba: ' . $e->getMessage());
        }

        $publishList = [];
        foreach (PackageHelper::PACKAGE_LIST as $name => $publish) {
            $this->publishes([
                $publish['source'] => base_path($publish['destination']),
            ], PackageHelper::PACKAGE_PREFIX . '-' . $name);

            $publishList[$publish['source']] = base_path($publish['destination']);
        }

        $this->publishes($publishList, PackageHelper::PACKAGE_PREFIX . '-all');
    }

    /**
     * Register the application services.
     */
    public function register()
    {
        // Config merge
        $this->mergeConfigFrom(
            __DIR__ . '/../config/commerce-barion.php',
            'commerce-barion'
        );

        $this->app->singleton(BarionSettingsService::class, function ($app) {
            return new BarionSettingsService();
        });

        // BarionPaymentProvider singleton regisztráció
        $this->app->singleton(BarionPaymentProvider::class, function ($app) {
            return new BarionPaymentProvider();
        });

        $this->commands([
            InstallCommerceBarionCommand::class,
            ExtendViewsCommerceBarionCommand::class,
        ]);
    }
}
