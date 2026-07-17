<?php

namespace Weboldalnet\CommerceBarion\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
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
        $result = $this->callbackProcessor->process($provider, $request->all());

        // Irányítás a webshop eredmény oldalára
        if ($result && $result->transactionId) {
            return redirect()->route('site.webshop.payment.result', ['order' => $result->transactionId]);
        }

        return redirect()->route('site.webshop.checkout.index')->with('error', 'Sikertelen fizetési visszatérés.');
    }

    /**
     * Barion szerver-szerver callback (IPN).
     */
    public function handleCallback(Request $request)
    {
        $provider = config('commerce-barion.provider_code', 'barion');
        $this->callbackProcessor->process($provider, $request->all());

        return response()->json(['success' => true]);
    }
}
