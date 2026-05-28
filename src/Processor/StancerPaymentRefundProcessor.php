<?php

declare(strict_types=1);

namespace SpiderWeb\Sylius\StancerPlugin\Processor;

use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Payment\Model\PaymentRequestInterface;
use Sylius\Component\Payment\Repository\PaymentRequestRepositoryInterface;
use Stancer\Config as StancerConfig;
use Stancer\Payment as StancerPayment;

final class StancerPaymentRefundProcessor
{
    private const GATEWAY_FACTORY_NAME = 'stancer';

    /** @param PaymentRequestRepositoryInterface<PaymentRequestInterface> $paymentRequestRepository */
    public function __construct(
        private PaymentRequestRepositoryInterface $paymentRequestRepository,
    ) {
    }

    public function refund(PaymentInterface $payment): void
    {
        $paymentMethod = $payment->getMethod();
        if (null === $paymentMethod) {
            return;
        }

        $gatewayConfig = $paymentMethod->getGatewayConfig();
        if (null === $gatewayConfig || $gatewayConfig->getFactoryName() !== self::GATEWAY_FACTORY_NAME) {
            return;
        }

        // Find a completed PaymentRequest for this payment to retrieve stancer_payment_id
        $paymentRequests = $this->paymentRequestRepository->findByPaymentIdAndStates(
            $payment->getId(),
            [PaymentRequestInterface::STATE_COMPLETED],
        );

        $stancerPaymentId = null;
        foreach ($paymentRequests as $paymentRequest) {
            $responseData = $paymentRequest->getResponseData();
            if (!empty($responseData['stancer_payment_id'])) {
                $stancerPaymentId = $responseData['stancer_payment_id'];
                break;
            }
        }

        if (null === $stancerPaymentId) {
            return;
        }

        $config = $gatewayConfig->getConfig();
        StancerConfig::init(array_values(array_filter([$config['secret_key'], $config['public_key'] ?? null])));

        $stancerPayment = new StancerPayment((string) $stancerPaymentId);
        $stancerPayment->refund((int) $payment->getAmount());
    }
}
