<?php

namespace Weboldalnet\CommerceBarion\Services;

use Barion\BarionClient;
use Barion\Enumerations\BarionEnvironment;
use Barion\Enumerations\Currency;
use Barion\Enumerations\UILocale;
use Barion\Enumerations\PaymentType;
use Barion\Enumerations\FundingSourceType;
use Barion\Models\Common\ItemModel;
use Barion\Models\Payment\PreparePaymentRequestModel;
use Barion\Models\Payment\PaymentTransactionModel;
use Weboldalnet\CommerceCore\Data\PaymentRequestData;
use Weboldalnet\CommerceCore\Services\ProviderLogger;

class BarionPaymentService
{
    protected $client;

    /** @var ProviderLogger|null */
    protected $logger;

    public function __construct()
    {
        $this->client = BarionClientFactory::create();

        try {
            $this->logger = app(ProviderLogger::class);
        } catch (\Throwable $e) {
            $this->logger = null;
        }
    }

    /**
     * Naplózás a commerce_provider_logs táblába, ha a commerce-barion.log_payloads engedélyezett.
     * A POSKey soha nem kerül bele a naplózott payloadba.
     */
    protected function logApiCall($endpoint, array $request, $response, $isSuccess, $errorMessage = null, $orderId = null)
    {
        if (!$this->logger || !config('commerce-barion.log_payloads', true)) {
            return;
        }

        try {
            $this->logger->logResponse(
                'payment',
                config('commerce-barion.provider_code', 'barion'),
                $endpoint,
                $request,
                self::toArray($response),
                $isSuccess ? 200 : 400,
                (bool) $isSuccess,
                $errorMessage,
                is_numeric($orderId) ? (int) $orderId : null
            );
        } catch (\Throwable $e) {
            // A naplózás soha ne buktassa el a fizetési folyamatot.
            \Illuminate\Support\Facades\Log::warning('Barion provider log hiba: ' . $e->getMessage());
        }
    }

    /**
     * Barion SDK válaszobjektum tiszta tömbbé alakítása (a beágyazott objektumokkal együtt).
     */
    protected static function toArray($value): ?array
    {
        if ($value === null) {
            return null;
        }
        $decoded = json_decode(json_encode($value), true);

        return is_array($decoded) ? $decoded : ['value' => $value];
    }

    /**
     * A fizetési kérés naplózható változata – POSKey nélkül.
     */
    protected static function loggableRequest(PaymentRequestData $data): array
    {
        return [
            'order_id' => $data->orderId,
            'order_number' => $data->orderNumber,
            'payment_request_id' => (string) ($data->orderNumber ?: $data->orderId),
            'amount' => $data->amount,
            'currency' => $data->currency,
            'language' => $data->language,
            'callback_url' => $data->callbackUrl,
            'return_url' => $data->returnUrl,
            'items' => $data->items,
            'environment' => config('commerce-barion.environment'),
            'payment_type' => config('commerce-barion.payment_type'),
        ];
    }

    /**
     * Elindít egy Barion fizetést.
     */
    public function startPayment(PaymentRequestData $data)
    {
        $request = new PreparePaymentRequestModel();
        
        $request->POSKey = config('commerce-barion.pos_key');
        
        try {
            $paymentType = config('commerce-barion.payment_type', 'Immediate');
            $request->PaymentType = PaymentType::from($paymentType);
        } catch (\Throwable $e) {
            $request->PaymentType = PaymentType::Immediate;
        }

        $request->PaymentWindow = config('commerce-barion.payment_window', '0.00:30:00');
        
        $fundingSources = config('commerce-barion.funding_sources', ['All']);
        $mappedFundingSources = [];
        foreach ($fundingSources as $source) {
            try {
                $mappedFundingSources[] = FundingSourceType::from($source);
            } catch (\Throwable $e) {
                // Skip invalid funding sources
            }
        }
        $request->FundingSources = !empty($mappedFundingSources) ? $mappedFundingSources : [FundingSourceType::All];

        $request->PaymentRequestId = (string)($data->orderNumber ?: $data->orderId);
        $request->PayerHint = config('commerce-barion.payer_hint_enabled', true) ? $data->customerEmail : null;
        $request->Locale = $this->mapLanguage($data->language);
        try {
            $request->Currency = Currency::from(strtoupper($data->currency));
        } catch (\Throwable $e) {
            $request->Currency = Currency::HUF;
        }
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

        try {
            $response = $this->client->PreparePayment($request);
        } catch (\Throwable $e) {
            $this->logApiCall('PreparePayment', self::loggableRequest($data), null, false, $e->getMessage(), $data->orderId);
            throw $e;
        }

        $isSuccess = (bool) ($response->RequestSuccessful ?? false);
        $errorMessage = null;
        if (!$isSuccess && !empty($response->Errors)) {
            $error = $response->Errors[0];
            $errorMessage = trim(($error->Title ?? '') . ': ' . ($error->Description ?? ''));
        }
        $this->logApiCall('PreparePayment', self::loggableRequest($data), $response, $isSuccess, $errorMessage, $data->orderId);

        return $response;
    }

    /**
     * Lekéri a fizetés állapotát.
     */
    public function getPaymentState(string $paymentId)
    {
        try {
            $response = $this->client->getPaymentState($paymentId);
        } catch (\Throwable $e) {
            $this->logApiCall('GetPaymentState', ['payment_id' => $paymentId], null, false, $e->getMessage());
            throw $e;
        }

        $this->logApiCall(
            'GetPaymentState',
            ['payment_id' => $paymentId],
            $response,
            (bool) ($response->RequestSuccessful ?? false)
        );

        return $response;
    }

    /**
     * Nyelv kód leképezése Barion formátumra.
     */
    protected function mapLanguage(?string $lang): UILocale
    {
        $lang = strtoupper($lang);
        return match ($lang) {
            'HU' => UILocale::HU,
            'EN' => UILocale::EN,
            'DE' => UILocale::DE,
            'SL' => UILocale::SL,
            'SK' => UILocale::SK,
            'FR' => UILocale::FR,
            'CZ' => UILocale::CZ,
            'GR' => UILocale::GR,
            'ES' => UILocale::ES,
            default => UILocale::from(config('commerce-barion.locale', 'hu-HU')),
        };
    }
}
