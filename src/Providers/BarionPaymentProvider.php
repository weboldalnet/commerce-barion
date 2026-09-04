<?php

namespace Weboldalnet\CommerceBarion\Providers;

use Weboldalnet\CommerceCore\Contracts\PaymentProviderInterface;
use Weboldalnet\CommerceCore\Data\PaymentCallbackResult;
use Weboldalnet\CommerceCore\Data\PaymentCreateResult;
use Weboldalnet\CommerceCore\Data\PaymentRefundData;
use Weboldalnet\CommerceCore\Data\PaymentRefundResult;
use Weboldalnet\CommerceCore\Data\PaymentRequestData;
use Weboldalnet\CommerceCore\Status\PaymentStatus;
use Weboldalnet\CommerceBarion\Services\BarionPaymentService;
use Weboldalnet\CommerceBarion\Services\BarionCallbackService;
use Weboldalnet\CommerceBarion\Services\BarionSettingsService;

class BarionPaymentProvider implements PaymentProviderInterface
{
    protected $paymentService;
    protected $callbackService;

    public function __construct()
    {
        $this->paymentService = new BarionPaymentService();
        $this->callbackService = new BarionCallbackService($this->paymentService);
    }

    public function getCode()
    {
        return config('commerce-barion.provider_code', 'barion');
    }

    public function getName()
    {
        // A pénztárban megjelenő elnevezés adminból szerkeszthető.
        return (string) BarionSettingsService::get('payment_method_label', 'Barion bankkártyás fizetés');
    }

    public function isOnline()
    {
        return true;
    }

    public function createPayment(PaymentRequestData $data)
    {
        try {
            $response = $this->paymentService->startPayment($data);
            if ($response->RequestSuccessful) {
                return new PaymentCreateResult([
                    'success' => true,
                    'status' => PaymentStatus::PENDING,
                    'provider' => $this->getCode(),
                    'provider_transaction_id' => $response->PaymentId,
                    'transaction_id' => $data->orderNumber ?: $data->orderId,
                    'redirect_url' => $response->PaymentRedirectUrl,
                    'message' => 'Barion fizetés elindítva.',
                    'raw_response' => (array)$response,
                ]);
            }

            $errorMessage = 'Ismeretlen hiba';
            if (!empty($response->Errors)) {
                $error = $response->Errors[0];
                $errorMessage = ($error->Title ?? '') . ': ' . ($error->Description ?? '');
            }

            return new PaymentCreateResult([
                'success' => false,
                'status' => PaymentStatus::FAILED,
                'message' => 'Barion hiba: ' . $errorMessage,
                'raw_response' => (array)$response,
            ]);

        } catch (\Throwable $e) {
            return new PaymentCreateResult([
                'success' => false,
                'status' => PaymentStatus::FAILED,
                'message' => 'Kivétel a Barion fizetés indításakor: ' . $e->getMessage(),
            ]);
        }
    }

    public function handleReturn(array $payload)
    {
        return $this->callbackService->handle($payload);
    }

    public function handleCallback(array $payload)
    {
        return $this->callbackService->handle($payload);
    }

    /**
     * Egy függőben lévő fizetés valódi állapotának lekérdezése a Bariontól.
     *
     * NEM része a PaymentProvider szerződésnek – a hívó method_exists()-tel nézi
     * meg, hogy a provider tudja-e. Akkor hasznos, amikor a Barion callbackje
     * nem ért el minket (pl. fejlesztői gép), vagy még nem érkezett meg.
     *
     * A Barion a SAJÁT PaymentId-jára kérdez (ez nálunk a provider_transaction_id),
     * nem a rendelésszámra – ezért kapjuk meg a teljes tranzakciót.
     * A callbackService->handle() amúgy is állapotlekérdezést végez
     * (GetPaymentState), tehát ugyanaz az út fut le, mint egy valódi callbacknél.
     *
     * @param mixed $transaction PaymentTransaction vagy Barion paymentId string
     */
    public function queryStatus($transaction)
    {
        $paymentId = is_object($transaction)
            ? ($transaction->provider_transaction_id ?? null)
            : $transaction;

        if (!$paymentId) {
            return null;
        }

        return $this->callbackService->handle(['paymentId' => $paymentId]);
    }
    public function refund(PaymentRefundData $data)
    {
        // Refund egyelőre not_supported
        return new PaymentRefundResult([
            'success' => false,
            'message' => 'A Barion refund funkció még nem támogatott ebben a verzióban.',
        ]);
    }
}
