<?php

declare(strict_types=1);

namespace SpiderWeb\Sylius\StancerPlugin\Form\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Validator\Constraints\NotBlank;

final class StancerGatewayConfigurationType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('secret_key', PasswordType::class, [
                'label' => 'sylius_stancer.ui.secret_key',
                'always_empty' => false,
                'constraints' => [new NotBlank()],
            ])
            ->add('public_key', TextType::class, [
                'label' => 'sylius_stancer.ui.public_key',
                'constraints' => [new NotBlank()],
            ])
        ;
    }

    public function getBlockPrefix(): string
    {
        return 'spiderweb_sylius_stancer_gateway_configuration';
    }
}
