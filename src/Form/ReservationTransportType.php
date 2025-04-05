<?php

namespace App\Form;

use App\Entity\ReservationTransport;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\TextType;

class ReservationTransportType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('adresseDepart', TextType::class, [
                'attr' => ['class' => 'form-control'],
                'label' => 'Adresse de départ'
            ])
            ->add('adresseDestination', TextType::class, [
                'attr' => ['class' => 'form-control'],
                'label' => 'Adresse de destination'
            ])
            ->add('tempsArrivage', TextType::class, [
                'attr' => ['class' => 'form-control'],
                'label' => 'Temps d\'arrivage',
                'required' => false
            ])
            ->add('cinEtudiant', TextType::class, [
                'attr' => ['class' => 'form-control'],
                'label' => 'CIN Étudiant',
                'required' => false
            ])
            ->add('cinTransporteur', TextType::class, [
                'attr' => ['class' => 'form-control'],
                'label' => 'CIN Transporteur',
                'required' => false
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => ReservationTransport::class,
        ]);
    }
}