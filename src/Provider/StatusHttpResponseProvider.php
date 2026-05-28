<?php

declare(strict_types=1);

namespace SpiderWeb\Sylius\StancerPlugin\Provider;

use Sylius\Bundle\CoreBundle\OrderPay\Provider\FinalUrlProviderInterface;
use Sylius\Bundle\PaymentBundle\Provider\HttpResponseProviderInterface;
use Sylius\Bundle\ResourceBundle\Controller\RequestConfiguration;
use Sylius\Component\Core\Model\PaymentInterface as CorePaymentInterface;
use Sylius\Component\Payment\Model\PaymentRequestInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;

final class StatusHttpResponseProvider implements HttpResponseProviderInterface
{
    public function __construct(private FinalUrlProviderInterface $finalUrlProvider)
    {
    }

    public function supports(
        RequestConfiguration $requestConfiguration,
        PaymentRequestInterface $paymentRequest,
    ): bool {
        return $paymentRequest->getAction() === PaymentRequestInterface::ACTION_STATUS;
    }

    public function getResponse(
        RequestConfiguration $requestConfiguration,
        PaymentRequestInterface $paymentRequest,
    ): Response {
        $payment = $paymentRequest->getPayment();
        $corePayment = $payment instanceof CorePaymentInterface ? $payment : null;

        return new RedirectResponse($this->finalUrlProvider->getUrl($corePayment));
    }
}
