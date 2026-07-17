<?php

namespace Weboldalnet\CommerceBarion;

use Illuminate\Support\ServiceProvider;
use Weboldalnet\CommerceCore\Managers\PaymentManager;
use Weboldalnet\CommerceBarion\Providers\BarionPaymentProvider;

class CommerceBarionServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap the application services.
     */
    public function boot()
    {
        // Config publikálhatóvá tétele
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../config/commerce-barion.php' => config_path('commerce-barion.php'),
            ], 'commerce-barion-config');
        }

        // Route-ok betöltése
        $this->loadRoutesFrom(__DIR__ . '/../routes/web.php');

        // Provider regisztráció a commerce-core-ba
        if (config('commerce-barion.enabled', true)) {
            try {
                $paymentManager = $this->app->make(PaymentManager::class);
                $paymentManager->register(
                    config('commerce-barion.provider_code', 'barion'),
                    $this->app->make(BarionPaymentProvider::class)
                );
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::error('Barion regisztrációs hiba: ' . $e->getMessage());
            }
        }
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

        // BarionPaymentProvider singleton regisztráció
        $this->app->singleton(BarionPaymentProvider::class, function ($app) {
            return new BarionPaymentProvider();
        });
    }
}
