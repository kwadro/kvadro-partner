<?php

namespace App\Form;

use App\Entity\RewardSettings;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Range;

class RewardSettingsFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $percentConstraints = [
            new NotBlank(message: 'admin.cabinet.settings.percent_required'),
            new Range(
                min: 0,
                max: 100,
                notInRangeMessage: 'admin.cabinet.settings.percent_range',
            ),
        ];

        $builder
            ->add('ownPercent', NumberType::class, [
                'label' => 'admin.cabinet.settings.own_percent',
                'scale' => 2,
                'html5' => true,
                'attr' => [
                    'min' => 0,
                    'max' => 100,
                    'step' => '0.01',
                ],
                'constraints' => $percentConstraints,
            ])
            ->add('level1Percent', NumberType::class, [
                'label' => 'admin.cabinet.settings.level1_percent',
                'scale' => 2,
                'html5' => true,
                'attr' => [
                    'min' => 0,
                    'max' => 100,
                    'step' => '0.01',
                ],
                'constraints' => $percentConstraints,
            ])
            ->add('level2Percent', NumberType::class, [
                'label' => 'admin.cabinet.settings.level2_percent',
                'scale' => 2,
                'html5' => true,
                'attr' => [
                    'min' => 0,
                    'max' => 100,
                    'step' => '0.01',
                ],
                'constraints' => $percentConstraints,
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => RewardSettings::class,
            'translation_domain' => 'messages',
        ]);
    }
}
