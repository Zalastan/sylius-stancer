<?php

declare(strict_types=1);

namespace SpiderWeb\Sylius\StancerPlugin\Form\Extension;

use Sylius\Bundle\PaymentBundle\Form\Type\GatewayConfigType;
use Symfony\Component\Form\AbstractTypeExtension;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;

final class StancerGatewayConfigTypeExtension extends AbstractTypeExtension
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        // PRE_SUBMIT fires after the user submits, with the raw HTTP data.
        // Force usePayum = false before it gets mapped to the entity.
        $builder->addEventListener(FormEvents::PRE_SUBMIT, static function (FormEvent $event): void {
            $data = $event->getData();
            if (($data['factoryName'] ?? null) !== 'stancer') {
                return;
            }
            $data['usePayum'] = false;
            $event->setData($data);
        });
    }

    public static function getExtendedTypes(): iterable
    {
        return [GatewayConfigType::class];
    }
}
