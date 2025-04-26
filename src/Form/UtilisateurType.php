<?php

namespace App\Form;

use App\Entity\Utilisateur;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use App\Enums\RoleUtilisateur;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\Image; // Ajoutez cette ligne pour les contraintes d'image
use Symfony\Component\Form\Extension\Core\Type\FileType; // Ajoutez cette ligne
use Symfony\Component\Form\Extension\Core\Type\ButtonType;
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;

class UtilisateurType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
        ->add('cin', null, [
            'constraints' => [
                new NotBlank(['message' => 'Le CIN est obligatoire']),
                new Length([
                    'min' => 8,
                    'max' => 8,
                    'exactMessage' => 'Le CIN doit contenir exactement 8 chiffres'
                ]),
            ],
        ])
        ->add('nom', null, [
            'constraints' => [
                new NotBlank(['message' => 'Le nom est obligatoire']),
            ],
        ])
        ->add('prenom', null, [
            'constraints' => [
                new NotBlank(['message' => 'Le prénom est obligatoire']),
            ],
        ])
        ->add('email', null, [
            'constraints' => [
                new NotBlank(['message' => 'L\'email est obligatoire']),
                new Email(['message' => 'Veuillez entrer un email valide']),
            ],
        ])
        ->add('numTel', null, [
            'constraints' => [
                new NotBlank(['message' => 'Le numéro de téléphone est obligatoire']),
            ],
        ])
            ->add('mdp', PasswordType::class, [
                'label' => 'Mot de passe',
                'constraints' => [
                    new NotBlank([
                        'message' => 'Please enter a password',
                    ]),
                    // Add other password constraints as needed
                ],
            ])
            ->add('role', EnumType::class, [
                'class' => RoleUtilisateur::class,
                'choice_label' => fn(RoleUtilisateur $role) => $role->value,
            ])
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

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Utilisateur::class,
        ]);
    }
}
