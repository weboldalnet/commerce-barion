<?php

namespace Weboldalnet\CommerceBarion\Services;

use Barion\BarionClient;
use Barion\Enumerations\BarionEnvironment;
use Barion\Enumerations\Currency;
use Barion\Enumerations\Language;
use Barion\Enumerations\PaymentType;
use Barion\Models\Common\ItemModel;
use Barion\Models\Payment\PaymentStartRequest;
use Barion\Models\Payment\PaymentTransactionModel;
use Weboldalnet\CommerceCore\Data\PaymentRequestData;

class BarionPaymentService
{
    protected $client;

    public function __construct()
    {
        $this->client = BarionClientFactory::create();
    }

    /**
     * Elindít egy Barion fizetést.
     */
    public function startPayment(PaymentRequestData $data)
    {
        $request = new PaymentStartRequest();
        
        $request->POSKey = config('commerce-barion.pos_key');
        $request->PaymentType = config('commerce-barion.payment_type', PaymentType::Immediate);
        $request->GuestCheckOut = config('commerce-barion.guest_checkout_enabled', true);
        $request->FundingSources = config('commerce-barion.funding_sources', ['All']);
        $request->PaymentRequestId = (string)($data->orderNumber ?: $data->orderId);
        $request->PayerHint = config('commerce-barion.payer_hint_enabled', true) ? $data->customerEmail : null;
        $request->Locale = $this->mapLanguage($data->language);
        $request->Currency = $data->currency;
        $request->CallbackUrl = $data->callbackUrl ?: (config('commerce-barion.callback_url') ?: route('commerce.barion.callback'));
        $request->RedirectUrl = $data->returnUrl ?: (config('commerce-barion.redirect_url') ?: route('commerce.barion.return'));
        
        // Tranzakció összeállítása
        $transaction = new PaymentTransactionModel();
        $transaction->POSTransactionId = (string)($data->orderNumber ?: $data->orderId);
        $transaction->Payee = config('commerce-barion.payee');
        $transaction->Total = (float)$data->amount;
        
        // Tételek hozzáadása
        if (!empty($data->items)) {
            foreach ($data->items as $itemData) {
                $item = new ItemModel();
                $item->Name = $itemData['name'] ?? 'Termék';
                $item->Description = $itemData['description'] ?? $item->Name;
                $item->Quantity = (float)($itemData['quantity'] ?? 1);
                $item->Unit = $itemData['unit'] ?? 'db';
                $item->UnitPrice = (float)($itemData['unit_price'] ?? 0);
                $item->ItemTotal = (float)($itemData['total_price'] ?? ($item->UnitPrice * $item->Quantity));
                $item->SKU = $itemData['sku'] ?? null;
                
                $transaction->AddItem($item);
            }
        } else {
            // Ha nincs tétel, egy generikus tételt adunk hozzá az összeggel
            $item = new ItemModel();
            $item->Name = 'Rendelés #' . ($data->orderNumber ?: $data->orderId);
            $item->Quantity = 1;
            $item->Unit = 'db';
            $item->UnitPrice = (float)$data->amount;
            $item->ItemTotal = (float)$data->amount;
            $transaction->AddItem($item);
        }
        
        $request->AddTransaction($transaction);
        
        return $this->client->paymentStart($request);
    }

    /**
     * Lekéri a fizetés állapotát.
     */
    public function getPaymentState(string $paymentId)
    {
        return $this->client->getPaymentState($paymentId);
    }

    /**
     * Nyelv kód leképezése Barion formátumra.
     */
    protected function mapLanguage(?string $lang): string
    {
        $lang = strtoupper($lang);
        return match ($lang) {
            'HU' => 'hu-HU',
            'EN' => 'en-US',
            'DE' => 'de-DE',
            'SL' => 'sl-SI',
            'SK' => 'sk-SK',
            'FR' => 'fr-FR',
            'CZ' => 'cs-CZ',
            'GR' => 'el-GR',
            'ES' => 'es-ES',
            default => config('commerce-barion.locale', 'hu-HU'),
        };
    }
}
