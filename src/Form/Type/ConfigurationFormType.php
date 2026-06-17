<?php

namespace Velox\MailSendVx\Form\Type;

use PrestaShopBundle\Form\Admin\Type\SwitchType;
use PrestaShopBundle\Form\Admin\Type\TranslatorAwareType;
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
