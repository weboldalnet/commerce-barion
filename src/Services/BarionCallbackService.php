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

            if (!$response || !$response->IsSuccessful()) {
                return new PaymentCallbackResult([
                    'success' => false,
                    'providerTransactionId' => $paymentId,
                    'message' => 'Nem sikerült lekérdezni a Barion fizetés állapotát.',
                    'rawPayload' => (array)$response,
                ]);
            }

            $status = $this->mapStatus($response->Status);
            
            // Az első tranzakció adatait vesszük alapul (Barionnál általában 1 tranzakció van egy fizetésben)
            $transaction = $response->Transactions[0] ?? null;

            return new PaymentCallbackResult([
                'success' => $status === PaymentStatus::PAID,
                'status' => $status,
                'provider' => 'barion',
                'providerTransactionId' => $paymentId,
                'transactionId' => $transaction ? $transaction->POSTransactionId : null,
                'amount' => $response->Total,
                'currency' => $response->Currency,
                'message' => 'Barion állapot: ' . $response->Status,
                'rawPayload' => (array)$response,
            ]);

        } catch (\Throwable $e) {
            return new PaymentCallbackResult([
                'success' => false,
                'providerTransactionId' => $paymentId,
                'message' => 'Hiba a Barion callback feldolgozása során: ' . $e->getMessage(),
            ]);
        }
    }

    /**
     * Barion státusz leképezése CommerceCore státuszra.
     */
    protected function mapStatus(string $barionStatus): string
    {
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
