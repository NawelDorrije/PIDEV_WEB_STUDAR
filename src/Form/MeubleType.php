<?php

namespace App\Form;

use App\Entity\GestionMeubles\Meuble;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\File;

class MeubleType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('nom')
            ->add('description')
            ->add('prix', NumberType::class, [
                'scale' => 2, // Enforce 2 decimal places
                'html5' => true, // Use HTML5 number input
                'attr' => [
                    'step' => '0.01', // Allow increments of 0.01
                    'min' => '0', // Enforce non-negative values
                    'placeholder' => '0.00',
                ],
                'invalid_message' => 'Le prix doit être un nombre valide (ex: 123.45).',
            ])
            ->add('statut', ChoiceType::class, [
                'choices' => [
                    'Disponible' => 'disponible',
                    'Indisponible' => 'indisponible',
                ],
            ])
            ->add('categorie', ChoiceType::class, [
                'choices' => [
                    'Neuf' => 'neuf',
                    'Occasion' => 'occasion',
                ],
            ])
            ->add('image', FileType::class, [
                'label' => 'Image du meuble',
                'mapped' => false,
                'required' => $options['is_edit'] ? false : true,
                'constraints' => [
                    new File([
                        'maxSize' => '5M',
                        'mimeTypes' => ['image/jpeg', 'image/png', 'image/webp'],
                        'mimeTypesMessage' => 'Veuillez uploader une image valide (JPG, PNG, WEBP).',
                    ]),
                ],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Meuble::class,
            'is_edit' => false,
        ]);
    }
}