<?php

namespace App\Form;

use App\Entity\ReservationTransport;
use App\Entity\Utilisateur;
use App\Repository\UtilisateurRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotNull;
use Symfony\Component\Validator\Constraints\Regex;

class ReservationTransportType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('adresseDepart', TextType::class, [
                'attr' => ['class' => 'transport-input'],
                'label' => 'Adresse de départ',
                'constraints' => [
                    new NotBlank(['message' => 'L\'adresse de départ est obligatoire']),
                    new Length([
                        'min' => 5,
                        'max' => 255,
                        'minMessage' => 'L\'adresse doit contenir au moins {{ limit }} caractères',
                        'maxMessage' => 'L\'adresse ne peut pas dépasser {{ limit }} caractères'
                    ])
                ]
            ])
            ->add('adresseDestination', TextType::class, [
                'attr' => ['class' => 'transport-input'],
                'label' => 'Adresse de destination',
                'constraints' => [
                    new NotBlank(['message' => 'L\'adresse de destination est obligatoire']),
                    new Length([
                        'min' => 5,
                        'max' => 255,
                        'minMessage' => 'L\'adresse doit contenir au moins {{ limit }} caractères',
                        'maxMessage' => 'L\'adresse ne peut pas dépasser {{ limit }} caractères'
                    ])
                ]
            ])
            ->add('tempsArrivage', TextType::class, [
              'attr' => ['class' => 'transport-input'],
              'label' => 'Temps d\'arrivage',
              'required' => false,
              'constraints' => [
                  new Regex([
                      'pattern' => '/^([01]?[0-9]|2[0-3]):[0-5][0-9]$/',
                      'message' => 'Le format doit être HH:MM (ex: 14:00)'
                  ])
              ]
          ])
            ->add('etudiant', EntityType::class, [
                'class' => Utilisateur::class,
                'choice_label' => function(Utilisateur $user) {
                    return $user->getNom().' '.$user->getPrenom();
                },
                'query_builder' => function (UtilisateurRepository $er) {
                    return $er->createQueryBuilder('u')
                        ->where("u.role = 'étudiant'")
                        ->orderBy('u.nom', 'ASC');
                },
                'attr' => ['class' => 'transport-input'],
                'label' => 'Étudiant',
                'constraints' => [
                    new NotNull(['message' => 'L\'étudiant est obligatoire'])
                ]
            ])
            ->add('transporteur', EntityType::class, [
                'class' => Utilisateur::class,
                'choice_label' => function(Utilisateur $user) {
                    return $user->getNom().' '.$user->getPrenom();
                },
                'query_builder' => function (UtilisateurRepository $er) {
                    return $er->createQueryBuilder('u')
                        ->where("u.role = 'transporteur'")
                        ->orderBy('u.nom', 'ASC');
                },
                'attr' => ['class' => 'transport-input'],
                'label' => 'Transporteur',
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