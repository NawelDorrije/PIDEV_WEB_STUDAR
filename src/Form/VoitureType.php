<?php

namespace App\Form;

use App\Entity\Voiture;
use App\Enums\GestionTransport\VoitureDisponibilite;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Image;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;


class VoitureType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('idVoiture', null, [
                'label' => 'ID Voiture',
            ])
            ->add('model')
            ->add('numSerie')
            ->add('photo', FileType::class, [
                'label' => 'Photo',
                'mapped' => false, // Important - this field isn't mapped to the entity
                'required' => false,
                'constraints' => [
                    new Image([
                        'maxSize' => '5M',
                        'mimeTypes' => ['image/jpeg', 'image/png', 'image/gif'],
                        'mimeTypesMessage' => 'Please upload a valid image (JPEG, PNG, GIF)'
                    ])
                ], 'attr' => [
                       'accept' => 'image/*'
                ]
         ])
                 
            ->add('disponibilite',  ChoiceType::class, [
                'choices' => array_combine(
                        array_map(fn($case) => $case->value, VoitureDisponibilite::cases()),
                        VoitureDisponibilite::cases()
                    ),
                'choice_value' => function (?VoitureDisponibilite $disponibilite) {
                        return $disponibilite?->value;
                    },
                'attr' => ['class' => 'form-select']
                ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Voiture::class,
        ]);
    }
}
