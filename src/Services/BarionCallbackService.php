<?php

namespace Weboldalnet\CommerceBarion\Services;

use Weboldalnet\CommerceCore\Data\PaymentCallbackResult;
use Weboldalnet\CommerceCore\Status\PaymentStatus;

class BarionCallbackService
{
    protected $paymentService;

    public function __construct(BarionPaymentService $paymentService)
    {
        $this->paymentService = $paymentService;
    }

    /**
     * Feldolgozza a Barion visszatérést vagy callback-et.
     */
    public function handle(array $payload): PaymentCallbackResult
    {
        $paymentId = $payload['paymentId'] ?? ($payload['PaymentId'] ?? null);

        if (!$paymentId) {
            return new PaymentCallbackResult([
                'success' => false,
                'message' => 'Hiányzó Barion PaymentId.',
            ]);
        }

        try {
            // Lekérjük a tényleges állapotot a Bariontól
            $response = $this->paymentService->getPaymentState($paymentId);

            if (!$response || !$response->RequestSuccessful) {
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
