<?php

declare(strict_types=1);

namespace SpiderWeb\Sylius\StancerPlugin\Action;

use Payum\Core\Action\ActionInterface;
use Payum\Core\ApiAwareInterface;
use Payum\Core\ApiAwareTrait;
use Payum\Core\Bridge\Spl\ArrayObject;
use Payum\Core\Exception\RequestNotSupportedException;
use Payum\Core\GatewayAwareInterface;
use Payum\Core\GatewayAwareTrait;
use Payum\Core\Reply\HttpRedirect;
use Payum\Core\Request\Capture;
use Stancer\Config as StancerConfig;
use Stancer\Payment as StancerPayment;

final class CaptureAction implements ActionInterface, ApiAwareInterface, GatewayAwareInterface
{
    use ApiAwareTrait;
    use GatewayAwareTrait;

    public function setApi($api): void
    {
        if (!is_array($api)) {
            throw new \Payum\Core\Exception\UnsupportedApiException('Not supported api given. It must be an array.');
        }
        $this->api = $api;
    }

    /**
     * @param Capture $request
     */
    public function execute($request): void
    {
        RequestNotSupportedException::assertSupports($this, $request);

        $model = ArrayObject::ensureArrayObject($request->getModel());

        // Retour depuis la page Stancer : StatusAction prendra le relais
        if (!empty($model['stancer_payment_id'])) {
            return;
        }

        StancerConfig::init(array_values(array_filter([$this->api['secret_key'], $this->api['public_key'] ?? null])));

        $payment = new StancerPayment();
        $payment->setAmount((int) $model['amount']); // montant en centimes
        $payment->setCurrency(strtolower((string) ($model['currency_code'] ?? 'eur')));
        // Stancer requires HTTPS; in dev the URL may be HTTP, so we force the scheme
        $afterUrl = str_replace('http://', 'https://', $request->getToken()->getAfterUrl());
        $payment->setReturnUrl($afterUrl);

        if (!empty($model['order_id'])) {
            $payment->setOrderId(substr((string) $model['order_id'], 0, 36));
        }

        if (!empty($model['description'])) {
            $payment->setDescription(substr((string) $model['description'], 0, 64));
        }

        $payment->send();

        $model['stancer_payment_id'] = $payment->getId();
        $model['stancer_status'] = $payment->getStatus();

        $hostedUrl = sprintf(
            'https://payment.stancer.com/%s/%s?lang=fr',
            $this->api['public_key'],
            $payment->getId()
        );

        throw new HttpRedirect($hostedUrl);
    }

    public function supports($request): bool
    {
        return $request instanceof Capture
            && $request->getModel() instanceof \ArrayAccess;
    }
}
