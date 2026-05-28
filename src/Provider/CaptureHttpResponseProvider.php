<?php

declare(strict_types=1);

namespace SpiderWeb\Sylius\StancerPlugin\Provider;

use Sylius\Bundle\PaymentBundle\Provider\HttpResponseProviderInterface;
use Sylius\Bundle\ResourceBundle\Controller\RequestConfiguration;
use Sylius\Component\Payment\Model\PaymentRequestInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;

final class CaptureHttpResponseProvider implements HttpResponseProviderInterface
{
    public function supports(
        RequestConfiguration $requestConfiguration,
        PaymentRequestInterface $paymentRequest,
    ): bool {
        return $paymentRequest->getAction() === PaymentRequestInterface::ACTION_CAPTURE
            && $paymentRequest->getState() === PaymentRequestInterface::STATE_PROCESSING;
    }

    public function getResponse(
        RequestConfiguration $requestConfiguration,
        PaymentRequestInterface $paymentRequest,
    ): Response {
        $responseData = $paymentRequest->getResponseData();
        $stancerPaymentId = $responseData['stancer_payment_id'] ?? null;
        $publicKey = $responseData['stancer_public_key'] ?? null;

        $hostedUrl = sprintf(
            'https://payment.stancer.com/%s/%s?lang=fr',
            $publicKey,
            $stancerPaymentId,
        );

        return new RedirectResponse($hostedUrl);
    }
}
