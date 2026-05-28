<?php

declare(strict_types=1);

namespace SpiderWeb\Sylius\StancerPlugin\CommandHandler;

use SpiderWeb\Sylius\StancerPlugin\Command\CapturePaymentRequest;
use Sylius\Abstraction\StateMachine\StateMachineInterface;
use Sylius\Bundle\PaymentBundle\Provider\PaymentRequestProviderInterface;
use Sylius\Component\Payment\Model\PaymentRequestInterface;
use Sylius\Component\Payment\PaymentRequestTransitions;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Stancer\Config as StancerConfig;
use Stancer\Payment as StancerPayment;

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

        // Already processing (e.g. user hit back from Stancer): do nothing
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
}
