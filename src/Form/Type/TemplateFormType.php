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
            ->add('context_type', ChoiceType::class, [
                'label' => $this->trans('Tipo de contexto', 'Modules.Mailsendvx.Admin'),
                'choices' => $options['context_choices'],
            ])
            ->add('event_name', ChoiceType::class, [
                'label' => $this->trans('Evento', 'Modules.Mailsendvx.Admin'),
                'choices' => $options['event_choices'],
                'required' => false,
                'placeholder' => $this->trans('Sin disparo instantáneo', 'Modules.Mailsendvx.Admin'),
            ])
            ->add('template_name', TextType::class, [
                'label' => $this->trans('Nombre de la plantilla', 'Modules.Mailsendvx.Admin'),
            ])
            ->add('subject', TextType::class, [
                'label' => $this->trans('Asunto', 'Modules.Mailsendvx.Admin'),
                'help' => $this->trans('Usa expresiones Twig como {{ order.reference }}, {{ customer.name }} o {{ shop.name }}.', 'Modules.Mailsendvx.Admin'),
            ])
            ->add('mail_template', ChoiceType::class, [
                'label' => $this->trans('Wrapper de correo', 'Modules.Mailsendvx.Admin'),
                'choices' => $options['wrapper_choices'],
                'help' => $this->trans('Selecciona uno de los wrappers disponibles en el submenú Wrapper.', 'Modules.Mailsendvx.Admin'),
            ])
            ->add('id_lang', ChoiceType::class, [
                'label' => $this->trans('Idioma', 'Modules.Mailsendvx.Admin'),
                'choices' => $options['language_choices'],
            ])
            ->add('id_shop', IntegerType::class, [
                'label' => $this->trans('Tienda', 'Modules.Mailsendvx.Admin'),
                'required' => false,
                'empty_data' => (string) $options['default_shop_id'],
            ])
            ->add('html_content', TextareaType::class, [
                'label' => $this->trans('Contenido HTML', 'Modules.Mailsendvx.Admin'),
                'help' => $this->trans('Usa Twig para loops y condiciones, por ejemplo {% for product in order.products %}...{% endfor %} o {% for item in cart.items %}...{% endfor %}.', 'Modules.Mailsendvx.Admin'),
                'attr' => ['rows' => 8],
            ])
            ->add('text_content', TextareaType::class, [
                'label' => $this->trans('Contenido de texto', 'Modules.Mailsendvx.Admin'),
                'help' => $this->trans('Este campo se regenera automáticamente desde el contenido HTML cada vez que se guarda la plantilla.', 'Modules.Mailsendvx.Admin'),
                'required' => false,
                'attr' => [
                    'rows' => 5,
                    'readonly' => 'readonly',
                ],
            ])
            ->add('active', SwitchType::class, [
                'label' => $this->trans('Activa', 'Modules.Mailsendvx.Admin'),
                'required' => false,
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        parent::configureOptions($resolver);
        $resolver->setDefaults([
            'data_class' => null,
            'allow_extra_fields' => true,
            'event_choices' => [],
            'context_choices' => [],
            'wrapper_choices' => [],
            'language_choices' => [],
            'default_shop_id' => 1,
            'form_theme' => '@PrestaShop/Admin/TwigTemplateForm/prestashop_ui_kit.html.twig',
        ]);
    }
}
