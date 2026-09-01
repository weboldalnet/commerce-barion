<?php

namespace Weboldalnet\CommerceBarion\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Log;
use Weboldalnet\CommerceCore\Services\PaymentCallbackProcessor;

class BarionCallbackController extends Controller
{
    protected $callbackProcessor;

    public function __construct(PaymentCallbackProcessor $callbackProcessor)
    {
        $this->callbackProcessor = $callbackProcessor;
    }

    /**
     * Felhasználó visszatérése a Barionról.
     */
    public function handleReturn(Request $request)
    {
        $provider = config('commerce-barion.provider_code', 'barion');

        try {
            // A process() tömböt ad vissza: ['skipped' => bool, 'transaction' => PaymentTransaction, 'result' => PaymentCallbackResult]
            $processResult = $this->callbackProcessor->process($provider, $request->all());
        } catch (\Throwable $e) {
            Log::error('Barion visszatérés feldolgozási hiba: ' . $e->getMessage());
            $processResult = null;
        }

        // Irányítás a webshop eredmény oldalára a rendelés azonosítója alapján
        $orderId = $processResult['transaction']->order_id ?? null;
        if ($orderId) {
            return redirect()->route('site.webshop.payment.result', ['order' => $orderId]);
        }

        return redirect()->route('site.webshop.checkout.index')->with('error', 'Sikertelen fizetési visszatérés.');
    }

    /**
     * Barion szerver-szerver callback (IPN).
     */
    public function handleCallback(Request $request)
    {
        $provider = config('commerce-barion.provider_code', 'barion');

        try {
            $processResult = $this->callbackProcessor->process($provider, $request->all());
        } catch (\Throwable $e) {
            Log::error('Barion callback feldolgozási hiba: ' . $e->getMessage());

            // Szándékosan 500: így a Barion újrapróbálja a callback kézbesítését.
            return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
        }

        return response()->json([
            'success' => true,
            'skipped' => $processResult['skipped'] ?? false,
        ]);
    }
}
