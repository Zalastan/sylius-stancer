<?php

declare(strict_types=1);

namespace SpiderWeb\Sylius\StancerPlugin;

use Payum\Core\Bridge\Spl\ArrayObject;
use Payum\Core\GatewayFactory;
use SpiderWeb\Sylius\StancerPlugin\Action\CaptureAction;
use SpiderWeb\Sylius\StancerPlugin\Action\ConvertPaymentAction;
use SpiderWeb\Sylius\StancerPlugin\Action\RefundAction;
use SpiderWeb\Sylius\StancerPlugin\Action\StatusAction;

final class StancerGatewayFactory extends GatewayFactory
{
    protected function populateConfig(ArrayObject $config): void
    {
        $config->defaults([
            'payum.factory_name'  => 'stancer',
            'payum.factory_title' => 'Stancer',

            'payum.action.capture'         => new CaptureAction(),
            'payum.action.convert_payment' => new ConvertPaymentAction(),
            'payum.action.status'           => new StatusAction(),
            'payum.action.refund'           => new RefundAction(),
        ]);

        if (false === (bool) $config['payum.api']) {
            $config['payum.default_options'] = [
                'secret_key' => '',
                'public_key' => '',
            ];
            $config->defaults($config['payum.default_options']);

            $config['payum.required_options'] = ['secret_key', 'public_key'];

            $config['payum.api'] = static function (ArrayObject $config): array {
                $config->validateNotEmpty($config['payum.required_options']);

                return [
                    'secret_key' => $config['secret_key'],
                    'public_key' => $config['public_key'],
                ];
            };
        }
    }
}
