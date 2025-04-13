<?php
// src/Form/UtilisateurEditType.php

namespace App\Form;

use App\Entity\Utilisateur;
use App\Enums\RoleUtilisateur;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Validator\Constraints\Image; // Ajoutez cette ligne pour les contraintes d'image
use Symfony\Component\Form\Extension\Core\Type\FileType;

class UtilisateurEditType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('cin', null, ['disabled' => true])
            ->add('nom')
            ->add('prenom')
            ->add('email')
            ->add('numTel')
            ->add('role', EnumType::class, [
                'class' => RoleUtilisateur::class,
                'disabled' => true
            ])
            ->add('blocked')
            ->add('imageFile', FileType::class, [
                'label' => 'Photo de profil',
                'required' => false,
                'mapped' => false, // Ce champ n'est pas mappé directement à l'entité
                'constraints' => [
                    new Image([
                        'maxSize' => '2M',
                        'mimeTypes' => [
                            'image/jpeg',
                            'image/png',
                            'image/gif',
                        ],
                        'mimeTypesMessage' => 'Veuillez uploader une image valide (JPEG, PNG ou GIF)',
                    ])
                ],
                'attr' => [
                    'accept' => 'image/jpeg,image/png,image/gif'
                ]
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class' => Utilisateur::class,
            'validation_groups' => ['edit'],
        ]);
    }
}