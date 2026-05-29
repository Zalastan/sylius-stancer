<?php

declare(strict_types=1);

namespace SpiderWeb\Sylius\StancerPlugin\Provider;

use Sylius\Bundle\PaymentBundle\Provider\HttpResponseProviderInterface;
use Sylius\Bundle\ResourceBundle\Controller\RequestConfiguration;
use Sylius\Component\Payment\Model\PaymentRequestInterface;
use Symfony\Component\HttpFoundation\Response;
use Twig\Environment;

final class CaptureHttpResponseProvider implements HttpResponseProviderInterface
{
    public function __construct(private Environment $twig)
    {
    }

    public function supports(
        RequestConfiguration $requestConfiguration,
        PaymentRequestInterface $paymentRequest,
    ): bool {
        // Only redirect to Stancer for a fresh capture (state=new transitions to processing
        // immediately in the handler, so we check for the stancer_payment_id being set).
        // On return from Stancer (state=processing → completed/failed after handler runs),
        // this provider is no longer invoked.
        return $paymentRequest->getAction() === PaymentRequestInterface::ACTION_CAPTURE
            && $paymentRequest->getState() === PaymentRequestInterface::STATE_PROCESSING
            && isset($paymentRequest->getResponseData()['stancer_payment_id']);
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

        $returnUrl = $requestConfiguration->getRequest()->getUri();

        return new Response(
            $this->twig->render('@SpiderWebSyliusStancerPlugin/redirect.html.twig', [
                'stancer_url' => $hostedUrl,
                'return_url' => $returnUrl,
            ]),
        );
    }
}
