<?php

declare(strict_types=1);

namespace SpiderWeb\Sylius\StancerPlugin\Action;

use Payum\Core\Action\ActionInterface;
use Payum\Core\ApiAwareInterface;
use Payum\Core\ApiAwareTrait;
use Payum\Core\Bridge\Spl\ArrayObject;
use Payum\Core\Exception\RequestNotSupportedException;
use Payum\Core\Request\Refund;
use Stancer\Config as StancerConfig;
use Stancer\Payment as StancerPayment;

final class RefundAction implements ActionInterface, ApiAwareInterface
{
    use ApiAwareTrait;

    public function setApi($api): void
    {
        if (!is_array($api)) {
            throw new \Payum\Core\Exception\UnsupportedApiException('Not supported api given. It must be an array.');
        }
        $this->api = $api;
    }

    /**
     * @param Refund $request
     */
    public function execute($request): void
    {
        RequestNotSupportedException::assertSupports($this, $request);

        $model = ArrayObject::ensureArrayObject($request->getModel());

        if (empty($model['stancer_payment_id'])) {
            return;
        }

        StancerConfig::init(array_values(array_filter([$this->api['secret_key'], $this->api['public_key'] ?? null])));

        $payment = new StancerPayment((string) $model['stancer_payment_id']);

        // Remboursement partiel si un montant est spécifié, sinon remboursement total
        if (!empty($model['refund_amount'])) {
            $payment->refund((int) $model['refund_amount']);
        } else {
            $payment->refund();
        }
    }

    public function supports($request): bool
    {
        return $request instanceof Refund
            && $request->getModel() instanceof \ArrayAccess;
    }
}
