<?php

namespace Velox\MailSendVx\Form\Type;

use PrestaShopBundle\Form\Admin\Type\ColorPickerType;
use PrestaShopBundle\Form\Admin\Type\TranslatorAwareType;
use PrestaShopBundle\Form\Admin\Type\SwitchType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
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
            ->add('provider', \Symfony\Component\Form\Extension\Core\Type\TextType::class, [
                'label' => $this->trans('Proveedor', 'Modules.Mailsendvx.Admin'),
                'required' => false,
                'disabled' => true,
            ])
            ->add('debug', SwitchType::class, [
                'label' => $this->trans('Modo debug', 'Modules.Mailsendvx.Admin'),
                'required' => false,
            ])
            ->add('primary_500', ColorPickerType::class, [
                'label' => $this->trans('Primary 500', 'Modules.Mailsendvx.Admin'),
                'required' => false,
                'empty_data' => '#1B3A5C',
            ])
            ->add('secondary_500', ColorPickerType::class, [
                'label' => $this->trans('Secondary 500', 'Modules.Mailsendvx.Admin'),
                'required' => false,
                'empty_data' => '#C4690A',
            ])
            ->add('neutral_500', ColorPickerType::class, [
                'label' => $this->trans('Neutral 500', 'Modules.Mailsendvx.Admin'),
                'required' => false,
                'empty_data' => '#6E6A62',
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
