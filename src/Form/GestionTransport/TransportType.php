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
use Doctrine\ORM\EntityRepository;
use Symfony\Component\Security\Core\Security;

class TransportType extends AbstractType
{
    private $security;

    public function __construct(Security $security)
    {
        $this->security = $security;
    }

    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $currentUser = $this->security->getUser();
        $transport = $options['data'];
        $isCompleted = $transport && $transport->getStatus() === TransportStatus::COMPLETE;
    
        $builder
        ->add('reservation', EntityType::class, [
            'class' => ReservationTransport::class,
            'choice_label' => function(ReservationTransport $reservation) use ($isCompleted) {
                // Always show the same format in dropdown options
                return $reservation->getAdresseDepart().' → '.$reservation->getAdresseDestination();
            },
            'label' => 'Reservation',
            'placeholder' => 'Select a reservation',
            'query_builder' => function(EntityRepository $er) {
                return $er->createQueryBuilder('r')
                    ->orderBy('r.id', 'ASC');
            }
        ])
            ->add('voiture', EntityType::class, [
                'class' => Voiture::class,
                'choice_label' => function(Voiture $voiture) {
                    return $voiture->getModel() . ' (' . $voiture->getNumSerie() . ')';
                },
                'label' => 'Voiture',
                'placeholder' => 'Choisir une voiture',
                'query_builder' => function (EntityRepository $er) use ($currentUser) {
                    return $er->createQueryBuilder('v')
                        ->where('v.utilisateur = :user')
                        ->setParameter('user', $currentUser)
                        ->orderBy('v.model', 'ASC');
                }
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
