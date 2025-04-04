<?php

namespace App\Form;

use App\Entity\ReservationLogement;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\DateType;

class ReservationLogementType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('dateDebut', DateType::class, [
                'widget' => 'single_text',
                'required' => false,
            ])
            ->add('dateFin', DateType::class, [
                'widget' => 'single_text',
                'required' => false,
            ])
            ->add('status', null, [
                'required' => false,
            ])
            ->add('cinEtudiant', null, [
                'required' => false,
            ])
            ->add('cinProprietaire', null, [
                'required' => false,
            ])
            ->add('idLogement', null, [
                'required' => false,
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => ReservationLogement::class,
        ]);
    }
}