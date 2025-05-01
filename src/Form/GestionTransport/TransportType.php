<?php

namespace App\Form\GestionTransport;

use App\Entity\GestionTransport\Transport;
use App\Entity\GestionTransport\Voiture;
use App\Enums\GestionTransport\TransportStatus;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use App\Entity\ReservationTransport;
use Doctrine\ORM\EntityRepository;
use Symfony\Component\Security\Core\Security;
use Symfony\Component\Validator\Constraints\GreaterThanOrEqual;

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
        $formType = $options['form_type'] ?? 'edit';

        $builder
            ->add('reservation', EntityType::class, [
                'class' => ReservationTransport::class,
                'choice_label' => function (ReservationTransport $reservation) {
                    return $reservation->getAdresseDepart() . ' → ' . $reservation->getAdresseDestination();
                },
                'label' => 'Réservation',
                'placeholder' => 'Sélectionner une réservation',
                'query_builder' => function (EntityRepository $er) {
                    return $er->createQueryBuilder('r')
                        ->orderBy('r.id', 'ASC');
                },
                'disabled' => $isCompleted,
                'attr' => ['class' => 'form-control'],
            ])
            ->add('voiture', EntityType::class, [
                'class' => Voiture::class,
                'choice_label' => function (Voiture $voiture) {
                    return $voiture->getModel() . ' (' . $voiture->getNumSerie() . ')';
                },
                'label' => 'Véhicule',
                'placeholder' => 'Choisir un véhicule',
                'query_builder' => function (EntityRepository $er) use ($currentUser) {
                    return $er->createQueryBuilder('v')
                        ->where('v.utilisateur = :user')
                        ->setParameter('user', $currentUser)
                        ->orderBy('v.model', 'ASC');
                },
                'disabled' => $isCompleted,
                'attr' => ['class' => 'form-control'],
            ])
            ->add('status', ChoiceType::class, [
                'choices' => array_combine(
                    array_map(fn($case) => $case->value, TransportStatus::cases()),
                    TransportStatus::cases()
                ),
                'choice_value' => function (?TransportStatus $status) {
                    return $status?->value;
                },
                'label' => 'Statut',
                'disabled' => $isCompleted,
                'attr' => ['class' => 'form-select'],
            ])
            ->add('tempsArrivageDisplay', TextType::class, [
                'label' => 'Heure d\'arrivée estimée',
                'mapped' => false,
                'required' => false,
                'attr' => [
                    'class' => 'form-control',
                    'readonly' => true,
                ],
            ])
            ->add('etudiantDisplay', TextType::class, [
                'label' => 'Étudiant',
                'mapped' => false,
                'required' => false,
                'attr' => [
                    'class' => 'form-control',
                    'readonly' => true,
                ],
            ]);

        if ($formType === 'edit') {
            $builder
                ->add('trajetEnKm', NumberType::class, [
                    'label' => 'Distance du trajet (km)',
                    'required' => true,
                    'attr' => ['class' => 'form-control'],
                    'disabled' => $isCompleted,
                    'constraints' => [
                        new GreaterThanOrEqual(['value' => 0, 'message' => 'La distance doit être positive.']),
                    ],
                ])
                ->add('tarif', NumberType::class, [
                    'label' => 'Tarif (TND)',
                    'required' => true,
                    'attr' => ['class' => 'form-control'],
                    'disabled' => $isCompleted,
                    'constraints' => [
                        new GreaterThanOrEqual(['value' => 0, 'message' => 'Le tarif doit être positif.']),
                    ],
                ])
                ->add('loadingTimeActual', IntegerType::class, [
                    'label' => 'Temps de chargement réel (minutes)',
                    'required' => false,
                    'attr' => ['class' => 'form-control', 'min' => 0],
                    'disabled' => $isCompleted,
                    'constraints' => [
                        new GreaterThanOrEqual(['value' => 0, 'message' => 'Le temps doit être positif.']),
                    ],
                ])
                ->add('unloadingTimeActual', IntegerType::class, [
                    'label' => 'Temps de déchargement réel (minutes)',
                    'required' => false,
                    'attr' => ['class' => 'form-control', 'min' => 0],
                    'disabled' => $isCompleted,
                    'constraints' => [
                        new GreaterThanOrEqual(['value' => 0, 'message' => 'Le temps doit être positif.']),
                    ],
                ])
                ->add('loadingTimeAllowed', IntegerType::class, [
                    'label' => 'Temps de chargement autorisé (minutes)',
                    'required' => true,
                    'attr' => ['class' => 'form-control', 'min' => 0],
                    'disabled' => $isCompleted,
                    'constraints' => [
                        new GreaterThanOrEqual(['value' => 0, 'message' => 'Le temps doit être positif.']),
                    ],
                ])
                ->add('unloadingTimeAllowed', IntegerType::class, [
                    'label' => 'Temps de déchargement autorisé (minutes)',
                    'required' => true,
                    'attr' => ['class' => 'form-control', 'min' => 0],
                    'disabled' => $isCompleted,
                    'constraints' => [
                        new GreaterThanOrEqual(['value' => 0, 'message' => 'Le temps doit être positif.']),
                    ],
                ])
                ->add('extraCost', NumberType::class, [
                    'label' => 'Coût supplémentaire (TND)',
                    'required' => false,
                    'attr' => ['class' => 'form-control', 'readonly' => true],
                    'disabled' => true,
                ]);
        }
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Transport::class,
            'form_type' => 'edit',
        ]);

        $resolver->setAllowedValues('form_type', ['new', 'edit']);
    }
}