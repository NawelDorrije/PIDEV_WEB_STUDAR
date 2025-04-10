<?php

namespace App\Form;

use App\Entity\Utilisateur;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use App\Enums\RoleUtilisateur;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\Image; // Ajoutez cette ligne pour les contraintes d'image
use Symfony\Component\Form\Extension\Core\Type\FileType; // Ajoutez cette ligne

class UtilisateurType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('cin')
             ->add('nom')
            ->add('prenom')
            ->add('email')
            ->add('mdp', PasswordType::class, [
                'label' => 'Mot de passe',
                'required' => false,
            ])
            ->add('numTel')
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
