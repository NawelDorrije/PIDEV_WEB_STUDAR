<?php

// src/Form/ReservationLogementType.php
namespace App\Form;

use App\Entity\ReservationLogement;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ReservationLogementType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('dateDebut', null, [
                'widget' => 'single_text',
                'required' => true,
            ])
            ->add('dateFin', null, [
                'widget' => 'single_text',
                'required' => true,
            ])
            ->add('cinProprietaire')
            ->add('cinEtudiant')
            ->add('idLogement');
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => ReservationLogement::class,
        ]);
    }
}