<?php

namespace App\Form\GestionTransport;

use App\Entity\GestionTransport\Transport;
use App\Entity\GestionTransport\Voiture;
use App\Enums\GestionTransport\TransportStatus;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use App\Entity\ReservationTransport;


class TransportType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('reservation', EntityType::class, [
                'class' => ReservationTransport::class,
                'choice_label' => function(ReservationTransport $reservation) {
                    return $reservation->getAdresseDepart() . ' → ' . $reservation->getAdresseDestination();
                },
                'label' => 'Reservation',
                'placeholder' => 'Select a reservation'
            ])
            ->add('voiture', EntityType::class, [
                'class' => Voiture::class,
                'choice_label' => 'model',
                'label' => 'Voiture',
                'placeholder' => 'Choisir une voiture'
            ])
            ->add('status', ChoiceType::class, [
                'choices' => array_combine(
                    array_map(fn($case) => $case->value, TransportStatus::cases()),
                    TransportStatus::cases()
                ),
                'choice_value' => function (?TransportStatus $status) {
                    return $status?->value;
                },
                'attr' => ['class' => 'form-select']
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Transport::class,
        ]);
    }
}
