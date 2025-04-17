<?php

namespace App\Form;

use App\Entity\Logement;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

class LogementType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('nbrChambre', NumberType::class, [
                'label' => 'Nombre de chambres',
                'attr' => [
                    'placeholder' => 'Nombre de chambres',
                    'min' => 1,
                ],
                'required' => true,
            ])
            ->add('prix', NumberType::class, [
                'label' => 'Prix (€)',
                'attr' => [
                    'placeholder' => 'Prix (€)',
                    'step' => '0.01',
                ],
                'required' => true,
            ])
            ->add('type', TextType::class, [
                'label' => 'Type de propriété',
                'attr' => [
                    'placeholder' => 'Type de propriété',
                ],
                'required' => true,
            ])
            ->add('description', TextareaType::class, [
                'label' => 'Description',
                'attr' => [
                    'placeholder' => 'Description',
                    'rows' => 5,
                ],
                'required' => true,
            ])
            ->add('photos', FileType::class, [
                'label' => false,
                'multiple' => true,
                'mapped' => false,
                'required' => false,
                'attr' => [
                    'accept' => 'image/jpeg,image/png,image/gif',
                    'style' => 'display:none;',
                    'id' => 'file-input', // Align with template
                    'multiple' => 'multiple',
                ],
                'constraints' => [
                    new Assert\All([
                        new Assert\File([
                            'maxSize' => '5M',
                            'mimeTypes' => ['image/jpeg', 'image/png', 'image/gif'],
                            'mimeTypesMessage' => 'Please upload a valid image (JPEG, PNG, GIF).',
                            'maxSizeMessage' => 'The file is too large. Maximum size is 5MB.',
                        ])
                    ])
                ],
            ])
            ->add('address', TextType::class, [
                'label' => 'Adresse',
                'mapped' => false,
                'attr' => [
                    'id' => 'logement_address',
                    'placeholder' => 'Entrez l\'adresse ou cliquez sur la carte',
                ],
                'required' => true,
                'constraints' => [
                    new Assert\NotBlank(['message' => 'Address cannot be empty.']),
                ],
            ])
            ->add('lat', NumberType::class, [
                'mapped' => false,
                'required' => false,
                'attr' => [
                    'style' => 'display: none;',
                ],
            ])
            ->add('lng', NumberType::class, [
                'mapped' => false,
                'required' => false,
                'attr' => [
                    'style' => 'display: none;',
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Logement::class,
        ]);
    }
}