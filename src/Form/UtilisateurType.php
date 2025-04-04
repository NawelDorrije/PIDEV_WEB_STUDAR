<?php

namespace App\Form;

use App\Entity\Utilisateur;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use App\Enums\RoleUtilisateur;
class UtilisateurType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('cin')
             ->add('nom')
            ->add('prenom')
            ->add('email')
            ->add('mdp')
            ->add('numTel')
            ->add('role', EnumType::class, [
                'class' => RoleUtilisateur::class,
                'choice_label' => fn(RoleUtilisateur $role) => $role->value,
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
