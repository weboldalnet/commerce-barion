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
        return config('commerce-barion.default_payment_method_label', 'Barion bankkártyás fizetés');
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

    public function refund(PaymentRefundData $data)
    {
        // Refund egyelőre not_supported
        return new PaymentRefundResult([
            'success' => false,
            'message' => 'A Barion refund funkció még nem támogatott ebben a verzióban.',
        ]);
    }
}
