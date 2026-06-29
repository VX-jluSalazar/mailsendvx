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
                'label' => $this->trans('Enable event capture', 'Modules.Mailsendvx.Admin'),
                'required' => false,
            ])
            ->add('provider', TextType::class, [
                'label' => $this->trans('Provider', 'Modules.Mailsendvx.Admin'),
                'required' => false,
                'disabled' => true,
            ])
            ->add('debug', SwitchType::class, [
                'label' => $this->trans('Debug mode', 'Modules.Mailsendvx.Admin'),
                'required' => false,
            ])
            ->add('abandoned_cart_enabled', SwitchType::class, [
                'label' => $this->trans('Enable abandoned cart detection', 'Modules.Mailsendvx.Admin'),
                'required' => false,
            ])
            ->add('abandoned_cart_delay_value', IntegerType::class, [
                'label' => $this->trans('Abandoned cart delay value', 'Modules.Mailsendvx.Admin'),
                'required' => false,
                'empty_data' => '1',
            ])
            ->add('abandoned_cart_delay_unit', ChoiceType::class, [
                'label' => $this->trans('Abandoned cart delay unit', 'Modules.Mailsendvx.Admin'),
                'choices' => [
                    'Minutes' => 'minute',
                    'Hours' => 'hour',
                    'Days' => 'day',
                    'Weeks' => 'week',
                ],
            ])
            ->add('abandoned_cart_require_customer', SwitchType::class, [
                'label' => $this->trans('Require customer email', 'Modules.Mailsendvx.Admin'),
                'required' => false,
            ])
            ->add('abandoned_cart_require_products', SwitchType::class, [
                'label' => $this->trans('Require products in cart', 'Modules.Mailsendvx.Admin'),
                'required' => false,
            ])
            ->add('abandoned_cart_cron_batch_size', IntegerType::class, [
                'label' => $this->trans('Abandoned cart batch size', 'Modules.Mailsendvx.Admin'),
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
