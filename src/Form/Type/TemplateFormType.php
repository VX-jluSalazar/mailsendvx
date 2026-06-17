<?php

namespace Velox\MailSendVx\Form\Type;

use PrestaShopBundle\Form\Admin\Type\SwitchType;
use PrestaShopBundle\Form\Admin\Type\TranslatorAwareType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class TemplateFormType extends TranslatorAwareType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('id_mailsendvx_template', HiddenType::class, ['required' => false])
            ->add('event_name', ChoiceType::class, [
                'label' => $this->trans('Event', 'Modules.Mailsendvx.Admin'),
                'choices' => $options['event_choices'],
            ])
            ->add('template_name', TextType::class, [
                'label' => $this->trans('Template name', 'Modules.Mailsendvx.Admin'),
            ])
            ->add('subject', TextType::class, [
                'label' => $this->trans('Subject', 'Modules.Mailsendvx.Admin'),
            ])
            ->add('mail_template', TextType::class, [
                'label' => $this->trans('Mail wrapper', 'Modules.Mailsendvx.Admin'),
                'required' => false,
            ])
            ->add('id_lang', ChoiceType::class, [
                'label' => $this->trans('Language', 'Modules.Mailsendvx.Admin'),
                'choices' => $options['language_choices'],
            ])
            ->add('id_shop', IntegerType::class, [
                'label' => $this->trans('Shop', 'Modules.Mailsendvx.Admin'),
                'required' => false,
                'empty_data' => (string) $options['default_shop_id'],
            ])
            ->add('html_content', TextareaType::class, [
                'label' => $this->trans('HTML content', 'Modules.Mailsendvx.Admin'),
                'attr' => ['rows' => 8],
            ])
            ->add('text_content', TextareaType::class, [
                'label' => $this->trans('Text content', 'Modules.Mailsendvx.Admin'),
                'required' => false,
                'attr' => ['rows' => 5],
            ])
            ->add('active', SwitchType::class, [
                'label' => $this->trans('Active', 'Modules.Mailsendvx.Admin'),
                'required' => false,
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        parent::configureOptions($resolver);
        $resolver->setDefaults([
            'data_class' => null,
            'event_choices' => [],
            'language_choices' => [],
            'default_shop_id' => 1,
            'form_theme' => '@PrestaShop/Admin/TwigTemplateForm/prestashop_ui_kit.html.twig',
        ]);
    }
}
