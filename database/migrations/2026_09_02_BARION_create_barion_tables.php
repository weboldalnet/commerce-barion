<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Barion beállítások tábla.
 *
 * A Barion hozzáférés (POSKey, kedvezményezett) és a fizetési viselkedés az
 * admin felületről is megadható legyen, ne csak .env-ből – így éles környezetben
 * nem kell fájlhoz nyúlni. A POSKey titkosítva tárolódik.
 *
 * Ugyanaz a séma, mint a commerce-gls és commerce-szamlazzhu csomagoknál.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('public.commerce_barion_settings')) {
            Schema::create('public.commerce_barion_settings', function (Blueprint $table) {
                $table->id();
                $table->string('key')->unique();
                $table->text('value')->nullable();
                // string, boolean, integer, json, encrypted
                $table->string('type')->default('string');
                $table->string('group')->default('general');
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('public.commerce_barion_settings');
    }
};
