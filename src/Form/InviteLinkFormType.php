<?php

namespace App\Form;

use App\Entity\InviteLink;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Regex;
use Symfony\Contracts\Translation\TranslatorInterface;

class InviteLinkFormType extends AbstractType
{
    public function __construct(
        private TranslatorInterface $translator,
    ) {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('title', TextType::class, [
                'label' => 'cabinet.invites.form.title',
                'attr' => [
                    'placeholder' => $this->translator->trans('cabinet.invites.form.title_placeholder'),
                    'maxlength' => 180,
                ],
                'constraints' => [
                    new NotBlank(message: 'cabinet.invites.form.title_required'),
                    new Length(max: 180),
                ],
            ])
            ->add('linkKey', TextType::class, [
                'label' => 'cabinet.invites.form.key',
                'attr' => [
                    'placeholder' => $this->translator->trans('cabinet.invites.form.key_placeholder'),
                    'maxlength' => 64,
                    'autocomplete' => 'off',
                ],
                'constraints' => [
                    new NotBlank(message: 'cabinet.invites.form.key_required'),
                    new Length(min: 3, max: 64, minMessage: 'cabinet.invites.form.key_invalid'),
                    new Regex(
                        pattern: '/^[a-zA-Z0-9_-]+$/',
                        message: 'cabinet.invites.form.key_invalid',
                    ),
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => InviteLink::class,
            'translation_domain' => 'messages',
        ]);
    }
}
