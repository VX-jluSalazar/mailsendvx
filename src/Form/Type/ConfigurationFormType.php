<?php

namespace Velox\MailSendVx\Form\Type;

use PrestaShopBundle\Form\Admin\Type\SwitchType;
use PrestaShopBundle\Form\Admin\Type\TranslatorAwareType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ConfigurationFormType extends TranslatorAwareType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('enabled', SwitchType::class, [
                'label' => $this->trans('Habilitar captura de eventos', 'Modules.Mailsendvx.Admin'),
                'required' => false,
            ])
            ->add('provider', TextType::class, [
                'label' => $this->trans('Proveedor', 'Modules.Mailsendvx.Admin'),
                'required' => false,
                'disabled' => true,
            ])
            ->add('debug', SwitchType::class, [
                'label' => $this->trans('Modo debug', 'Modules.Mailsendvx.Admin'),
                'required' => false,
            ])
            ->add('abandoned_cart_enabled', SwitchType::class, [
                'label' => $this->trans('Habilitar detección de carrito abandonado', 'Modules.Mailsendvx.Admin'),
                'required' => false,
            ])
            ->add('abandoned_cart_delay_value', IntegerType::class, [
                'label' => $this->trans('Valor del retraso de carrito abandonado', 'Modules.Mailsendvx.Admin'),
                'required' => false,
                'empty_data' => '1',
            ])
            ->add('abandoned_cart_delay_unit', ChoiceType::class, [
                'label' => $this->trans('Unidad del retraso de carrito abandonado', 'Modules.Mailsendvx.Admin'),
                'choices' => [
                    'Minutos' => 'minute',
                    'Horas' => 'hour',
                    'Días' => 'day',
                    'Semanas' => 'week',
                ],
            ])
            ->add('abandoned_cart_require_customer', SwitchType::class, [
                'label' => $this->trans('Requerir correo del cliente', 'Modules.Mailsendvx.Admin'),
                'required' => false,
            ])
            ->add('abandoned_cart_require_products', SwitchType::class, [
                'label' => $this->trans('Requerir productos en el carrito', 'Modules.Mailsendvx.Admin'),
                'required' => false,
            ])
            ->add('abandoned_cart_cron_batch_size', IntegerType::class, [
                'label' => $this->trans('Tamaño del lote de carrito abandonado', 'Modules.Mailsendvx.Admin'),
                'required' => false,
                'empty_data' => '100',
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        parent::configureOptions($resolver);
        $resolver->setDefaults([
            'form_theme' => '@PrestaShop/Admin/TwigTemplateForm/prestashop_ui_kit.html.twig',
        ]);
    }
}
