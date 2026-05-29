<?php

declare(strict_types=1);

namespace SpiderWeb\Sylius\StancerPlugin\Processor;

use Sylius\Abstraction\StateMachine\StateMachineInterface;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Payment\Factory\PaymentRequestFactoryInterface;
use Sylius\Component\Payment\Model\PaymentRequestInterface;
use Sylius\Component\Payment\PaymentRequestTransitions;
use Sylius\Component\Payment\Repository\PaymentRequestRepositoryInterface;
use Stancer\Config as StancerConfig;
use Stancer\Payment as StancerPayment;
use Stancer\Refund as StancerRefund;

final class StancerPaymentRefundProcessor
{
    private const GATEWAY_FACTORY_NAME = 'stancer';

    /**
     * @param PaymentRequestFactoryInterface<PaymentRequestInterface> $paymentRequestFactory
     * @param PaymentRequestRepositoryInterface<PaymentRequestInterface> $paymentRequestRepository
     */
    public function __construct(
        private PaymentRequestFactoryInterface $paymentRequestFactory,
        private PaymentRequestRepositoryInterface $paymentRequestRepository,
        private StateMachineInterface $stateMachine,
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
        $completedRequests = $this->paymentRequestRepository->findByPaymentIdAndStates(
            $payment->getId(),
            [PaymentRequestInterface::STATE_COMPLETED],
        );

        $captureRequest = null;
        $stancerPaymentId = null;
        foreach ($completedRequests as $paymentRequest) {
            if ($paymentRequest->getAction() !== PaymentRequestInterface::ACTION_CAPTURE) {
                continue;
            }
            $responseData = $paymentRequest->getResponseData();
            if (!empty($responseData['stancer_payment_id'])) {
                $captureRequest = $paymentRequest;
                $stancerPaymentId = $responseData['stancer_payment_id'];
                break;
            }
        }

        if (null === $captureRequest || null === $stancerPaymentId) {
            return;
        }

        // Create a refund PaymentRequest to track the operation
        $refundRequest = $this->paymentRequestFactory->createFromPaymentRequest($captureRequest);
        $refundRequest->setAction(PaymentRequestInterface::ACTION_REFUND);
        $this->paymentRequestRepository->add($refundRequest);

        $config = $gatewayConfig->getConfig();
        StancerConfig::init(array_values(array_filter([$config['secret_key'], $config['public_key'] ?? null])));

        try {
            $stancerPayment = new StancerPayment((string) $stancerPaymentId);
            $refund = new StancerRefund();
            $refund->setPayment($stancerPayment);
            $refund->setAmount((int) $payment->getAmount());
            $refund->send();

            $refundRequest->setResponseData([
                'stancer_payment_id' => $stancerPaymentId,
                'stancer_refund_id' => $refund->getId(),
                'refunded_amount' => $payment->getAmount(),
            ]);

            $this->stateMachine->apply($refundRequest, PaymentRequestTransitions::GRAPH, PaymentRequestTransitions::TRANSITION_COMPLETE);
        } catch (\Throwable $e) {
            $refundRequest->setResponseData([
                'stancer_payment_id' => $stancerPaymentId,
                'error' => $e->getMessage(),
            ]);
            $this->stateMachine->apply($refundRequest, PaymentRequestTransitions::GRAPH, PaymentRequestTransitions::TRANSITION_FAIL);
        }
    }
}
