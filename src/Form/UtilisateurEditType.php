<?php
// src/Form/UtilisateurEditType.php

namespace App\Form;

use App\Entity\Utilisateur;
use App\Enums\RoleUtilisateur;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\EnumType;

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
            ->add('blocked');
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class' => Utilisateur::class,
            'validation_groups' => ['edit'],
        ]);
    }
}