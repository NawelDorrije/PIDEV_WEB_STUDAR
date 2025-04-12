<?php
// src/Form/LogementType.php

namespace App\Form;

use App\Entity\Logement;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;

class LogementType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('nbrChambre')
            ->add('prix')
            ->add('type')
            ->add('description')
            ->add('address', TextType::class, [
                'mapped' => false,
                'attr' => ['id' => 'location-input']
            ])
            ->add('latitude', HiddenType::class, [
                'mapped' => false
            ])
            ->add('longitude', HiddenType::class, [
                'mapped' => false
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Logement::class,
        ]);
    }
}