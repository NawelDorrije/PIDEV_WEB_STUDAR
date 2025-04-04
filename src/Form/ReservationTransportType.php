<?php

namespace App\Form;

use App\Entity\ReservationTransport;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ReservationTransportType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('adresseDepart')
            ->add('adresseDestination')
            ->add('tempsArrivage')
            ->add('cinEtudiant')
            ->add('cinTransporteur')
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => ReservationTransport::class,
        ]);
    }
}
