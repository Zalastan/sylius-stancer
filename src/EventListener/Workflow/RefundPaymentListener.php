<?php

declare(strict_types=1);

namespace SpiderWeb\Sylius\StancerPlugin\EventListener\Workflow;

use SpiderWeb\Sylius\StancerPlugin\Processor\StancerPaymentRefundProcessor;
use Sylius\Component\Core\Model\PaymentInterface;
use Symfony\Component\Workflow\Event\TransitionEvent;
use Webmozart\Assert\Assert;

final class RefundPaymentListener
{
    public function __construct(private StancerPaymentRefundProcessor $refundProcessor)
    {
    }

    public function __invoke(TransitionEvent $event): void
    {
        $payment = $event->getSubject();
        Assert::isInstanceOf($payment, PaymentInterface::class);

        $this->refundProcessor->refund($payment);
    }
}
