<?php

declare(strict_types=1);

namespace SpiderWeb\Sylius\StancerPlugin\Action;

use Payum\Core\Action\ActionInterface;
use Payum\Core\Exception\RequestNotSupportedException;
use Payum\Core\Request\Convert;
use Sylius\Component\Core\Model\PaymentInterface;

final class ConvertPaymentAction implements ActionInterface
{
    public function execute($request): void
    {
        RequestNotSupportedException::assertSupports($this, $request);

        /** @var PaymentInterface $payment */
        $payment = $request->getSource();
        $order = $payment->getOrder();

        $request->setResult([
            'amount'        => $payment->getAmount(),
            'currency_code' => $payment->getCurrencyCode(),
            'order_id'      => $order?->getNumber(),
            'description'   => 'Order #' . $order?->getNumber(),
        ]);
    }

    public function supports($request): bool
    {
        return $request instanceof Convert
            && $request->getSource() instanceof PaymentInterface
            && $request->getTo() === 'array';
    }
}
