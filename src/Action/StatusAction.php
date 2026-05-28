<?php

declare(strict_types=1);

namespace SpiderWeb\Sylius\StancerPlugin\Action;

use Payum\Core\Action\ActionInterface;
use Payum\Core\ApiAwareInterface;
use Payum\Core\ApiAwareTrait;
use Payum\Core\Bridge\Spl\ArrayObject;
use Payum\Core\Exception\RequestNotSupportedException;
use Payum\Core\Request\GetStatusInterface;
use Stancer\Config as StancerConfig;
use Stancer\Payment as StancerPayment;

final class StatusAction implements ActionInterface, ApiAwareInterface
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
     * @param GetStatusInterface $request
     */
    public function execute($request): void
    {
        RequestNotSupportedException::assertSupports($this, $request);

        $model = ArrayObject::ensureArrayObject($request->getModel());

        if (empty($model['stancer_payment_id'])) {
            $request->markNew();

            return;
        }

        StancerConfig::init($this->api['secret_key']);

        $payment = new StancerPayment((string) $model['stancer_payment_id']);
        $status = $payment->getStatus();
        $model['stancer_status'] = $status;

        match ($status) {
            'captured', 'to_capture', 'capture_sent' => $request->markCaptured(),
            'authorized'                              => $request->markAuthorized(),
            'canceled'                                => $request->markCanceled(),
            'failed', 'refused'                       => $request->markFailed(),
            'expired'                                 => $request->markExpired(),
            default                                   => $request->markUnknown(),
        };
    }

    public function supports($request): bool
    {
        return $request instanceof GetStatusInterface
            && $request->getModel() instanceof \ArrayAccess;
    }
}
