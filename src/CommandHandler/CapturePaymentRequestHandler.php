<?php

declare(strict_types=1);

namespace SpiderWeb\Sylius\StancerPlugin\CommandHandler;

use SpiderWeb\Sylius\StancerPlugin\Command\CapturePaymentRequest;
use Sylius\Abstraction\StateMachine\StateMachineInterface;
use Sylius\Bundle\PaymentBundle\Provider\PaymentRequestProviderInterface;
use Sylius\Component\Payment\Model\PaymentInterface;
use Sylius\Component\Payment\Model\PaymentRequestInterface;
use Sylius\Component\Payment\PaymentRequestTransitions;
use Sylius\Component\Payment\PaymentTransitions;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Stancer\Config as StancerConfig;
use Stancer\Payment as StancerPayment;
use Stancer\Payment\Status as StancerStatus;

#[AsMessageHandler]
final class CapturePaymentRequestHandler
{
    public function __construct(
        private PaymentRequestProviderInterface $paymentRequestProvider,
        private StateMachineInterface $stateMachine,
        private UrlGeneratorInterface $urlGenerator,
    ) {
    }

    public function __invoke(CapturePaymentRequest $command): void
    {
        $paymentRequest = $this->paymentRequestProvider->provide($command);

        if ($paymentRequest->getState() === PaymentRequestInterface::STATE_PROCESSING) {
            // User is returning from the Stancer hosted page: check actual payment status
            $this->handleReturn($paymentRequest);
            return;
        }

        if ($paymentRequest->getState() !== PaymentRequestInterface::STATE_NEW) {
            return;
        }

        /** @var \Sylius\Component\Core\Model\PaymentInterface $payment */
        $payment = $paymentRequest->getPayment();
        $order = $payment->getOrder();

        $gatewayConfig = $paymentRequest->getMethod()->getGatewayConfig();
        $config = $gatewayConfig->getConfig();

        StancerConfig::init(array_values(array_filter([$config['secret_key'], $config['public_key'] ?? null])));

        $stancerPayment = new StancerPayment();
        $stancerPayment->setAmount((int) $payment->getAmount());
        $stancerPayment->setCurrency(strtolower((string) ($payment->getCurrencyCode() ?? 'eur')));

        // Generate return URL pointing back to /pay/{hash} so Sylius processes the Stancer callback
        $returnUrl = $this->urlGenerator->generate(
            'sylius_shop_payment_request_pay',
            ['hash' => (string) $paymentRequest->getHash()],
            UrlGeneratorInterface::ABSOLUTE_URL,
        );
        // Stancer requires HTTPS; in dev the URL may be HTTP, force HTTPS
        $returnUrl = str_replace('http://', 'https://', $returnUrl);
        $stancerPayment->setReturnUrl($returnUrl);

        if ($order?->getNumber() !== null) {
            $stancerPayment->setOrderId(substr((string) $order->getNumber(), 0, 36));
        }

        $description = 'Order #' . ($order?->getNumber() ?? '');
        $stancerPayment->setDescription(substr($description, 0, 64));

        $stancerPayment->send();

        $responseData = $paymentRequest->getResponseData();
        $responseData['stancer_payment_id'] = $stancerPayment->getId();
        $responseData['stancer_status'] = $stancerPayment->getStatus()?->value;
        $responseData['stancer_public_key'] = $config['public_key'];
        $paymentRequest->setResponseData($responseData);

        $this->stateMachine->apply(
            $paymentRequest,
            PaymentRequestTransitions::GRAPH,
            PaymentRequestTransitions::TRANSITION_PROCESS,
        );
    }

    private function handleReturn(PaymentRequestInterface $paymentRequest): void
    {
        $responseData = $paymentRequest->getResponseData();
        $stancerPaymentId = $responseData['stancer_payment_id'] ?? null;

        if (null === $stancerPaymentId) {
            $this->stateMachine->apply($paymentRequest, PaymentRequestTransitions::GRAPH, PaymentRequestTransitions::TRANSITION_FAIL);
            return;
        }

        $gatewayConfig = $paymentRequest->getMethod()->getGatewayConfig();
        $config = $gatewayConfig->getConfig();
        StancerConfig::init(array_values(array_filter([$config['secret_key'], $config['public_key'] ?? null])));

        $stancerPayment = new StancerPayment((string) $stancerPaymentId);
        $status = $stancerPayment->getStatus();

        $responseData['stancer_status'] = $status?->value;
        $paymentRequest->setResponseData($responseData);

        /** @var PaymentInterface $payment */
        $payment = $paymentRequest->getPayment();

        match ($status) {
            StancerStatus::CAPTURED,
            StancerStatus::TO_CAPTURE,
            StancerStatus::CAPTURE_SENT,
            StancerStatus::CAPTURE => $this->applyPaymentTransition($payment, PaymentTransitions::TRANSITION_COMPLETE),
            StancerStatus::AUTHORIZED,
            StancerStatus::AUTHORIZE => $this->applyPaymentTransition($payment, PaymentTransitions::TRANSITION_AUTHORIZE),
            StancerStatus::CANCELED => $this->applyPaymentTransition($payment, PaymentTransitions::TRANSITION_CANCEL),
            StancerStatus::FAILED,
            StancerStatus::REFUSED => $this->applyPaymentTransition($payment, PaymentTransitions::TRANSITION_FAIL),
            default => null,
        };

        $paymentRequestTransition = match ($status) {
            StancerStatus::CAPTURED,
            StancerStatus::TO_CAPTURE,
            StancerStatus::CAPTURE_SENT,
            StancerStatus::CAPTURE,
            StancerStatus::AUTHORIZED,
            StancerStatus::AUTHORIZE => PaymentRequestTransitions::TRANSITION_COMPLETE,
            StancerStatus::CANCELED,
            StancerStatus::FAILED,
            StancerStatus::REFUSED => PaymentRequestTransitions::TRANSITION_FAIL,
            default => null,
        };

        if (null !== $paymentRequestTransition) {
            $this->stateMachine->apply($paymentRequest, PaymentRequestTransitions::GRAPH, $paymentRequestTransition);
        }
    }

    private function applyPaymentTransition(PaymentInterface $payment, string $transition): void
    {
        if ($this->stateMachine->can($payment, PaymentTransitions::GRAPH, $transition)) {
            $this->stateMachine->apply($payment, PaymentTransitions::GRAPH, $transition);
        }
    }
}
