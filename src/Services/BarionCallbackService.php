<?php

namespace Weboldalnet\CommerceBarion\Services;

use Weboldalnet\CommerceCore\Data\PaymentCallbackResult;
use Weboldalnet\CommerceCore\Services\ProviderLogger;
use Weboldalnet\CommerceCore\Status\PaymentStatus;

class BarionCallbackService
{
    protected $paymentService;

    /** @var ProviderLogger|null */
    protected $logger;

    public function __construct(BarionPaymentService $paymentService)
    {
        $this->paymentService = $paymentService;

        try {
            $this->logger = app(ProviderLogger::class);
        } catch (\Throwable $e) {
            $this->logger = null;
        }
    }

    /**
     * A beérkezett Barion visszatérés/callback naplózása a commerce_provider_logs táblába.
     */
    protected function logCallback(array $payload, $isSuccess, $errorMessage = null, $orderId = null): void
    {
        if (!$this->logger || !BarionSettingsService::getBool('log_payloads', true)) {
            return;
        }

        try {
            $this->logger->logCallback(
                'payment',
                config('commerce-barion.provider_code', 'barion'),
                $payload,
                (bool) $isSuccess,
                $errorMessage,
                is_numeric($orderId) ? (int) $orderId : null
            );
        } catch (\Throwable $e) {
            // A naplózás soha ne buktassa el a callback feldolgozását.
            \Illuminate\Support\Facades\Log::warning('Barion callback log hiba: ' . $e->getMessage());
        }
    }

    /**
     * Feldolgozza a Barion visszatérést vagy callback-et.
     */
    public function handle(array $payload): PaymentCallbackResult
    {
        $paymentId = $payload['paymentId'] ?? ($payload['PaymentId'] ?? null);

        if (!$paymentId) {
            $this->logCallback($payload, false, 'Hiányzó Barion PaymentId.');

            return new PaymentCallbackResult([
                'success' => false,
                'message' => 'Hiányzó Barion PaymentId.',
            ]);
        }

        try {
            // Lekérjük a tényleges állapotot a Bariontól
            $response = $this->paymentService->getPaymentState($paymentId);

            if (!$response || !$response->RequestSuccessful) {
                $this->logCallback($payload, false, 'Nem sikerült lekérdezni a Barion fizetés állapotát.');

                return new PaymentCallbackResult([
                    'success' => false,
                    'provider_transaction_id' => $paymentId,
                    'message' => 'Nem sikerült lekérdezni a Barion fizetés állapotát.',
                    'raw_payload' => (array)$response,
                ]);
            }

            $status = $this->mapStatus($response->Status);

            // Az első tranzakció adatait vesszük alapul (Barionnál általában 1 tranzakció van egy fizetésben)
            $transaction = $response->Transactions[0] ?? null;
            $orderId = $transaction ? $transaction->POSTransactionId : null;

            $this->logCallback($payload, $status === PaymentStatus::PAID, null, $orderId);

            return new PaymentCallbackResult([
                'success' => $status === PaymentStatus::PAID,
                'status' => $status,
                'provider' => 'barion',
                'provider_transaction_id' => $paymentId,
                'transaction_id' => $orderId,
                'order_id' => $orderId,
                'amount' => $response->Total,
                'currency' => $response->Currency instanceof \BackedEnum ? $response->Currency->value : (string)$response->Currency,
                'message' => 'Barion állapot: ' . ($response->Status instanceof \BackedEnum ? $response->Status->value : (string)$response->Status),
                'raw_payload' => (array)$response,
            ]);

        } catch (\Throwable $e) {
            $this->logCallback($payload, false, $e->getMessage());

            return new PaymentCallbackResult([
                'success' => false,
                'provider_transaction_id' => $paymentId,
                'message' => 'Hiba a Barion callback feldolgozása során: ' . $e->getMessage(),
            ]);
        }
    }

    /**
     * Barion státusz leképezése CommerceCore státuszra.
     */
    protected function mapStatus($barionStatus): string
    {
        if ($barionStatus instanceof \Barion\Enumerations\PaymentStatus) {
            $barionStatus = $barionStatus->value;
        }

        return match ($barionStatus) {
            'Succeeded', 'Completed' => PaymentStatus::PAID,
            'Failed' => PaymentStatus::FAILED,
            'Canceled', 'Cancelled' => PaymentStatus::CANCELLED,
            'Waiting', 'Prepared', 'InProgress', 'Authorized' => PaymentStatus::PENDING,
            'Expired' => PaymentStatus::FAILED,
            'Refunded', 'PartiallyRefunded' => PaymentStatus::REFUNDED,
            default => PaymentStatus::PENDING,
        };
    }
}
