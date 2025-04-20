<?php

namespace App\Form;

use App\Entity\ReservationTransport;
use App\Entity\Utilisateur;
use App\Form\DataTransformer\DateTimeToStringTransformer;
use App\Repository\UtilisateurRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotNull;
use Symfony\Component\Validator\Constraints\Callback;
use Symfony\Component\Validator\Context\ExecutionContextInterface;
use Symfony\Component\Validator\Constraints\GreaterThanOrEqual;
use Symfony\Component\Validator\Constraints\LessThanOrEqual;

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
            ->add('tempsArrivage', DateTimeType::class, [
                'attr' => [
                    'class' => 'transport-input',
                    'placeholder' => ' ',
                    'min' => (new \DateTime())->format('Y-m-d\TH:i'),
                ],
                'label' => 'Temps d\'arrivage',
                'widget' => 'single_text',
                'html5' => true,
                'constraints' => [
                    new NotBlank(['message' => 'La date est obligatoire']),
                    new Callback([
                        'callback' => function($value, ExecutionContextInterface $context) {
                            if ($value instanceof \DateTimeInterface) {
                                $now = new \DateTime();
                                $maxDate = (new \DateTime())->modify('+3 months');
                                
                                if ($value < $now) {
                                    $context->buildViolation('La date doit être aujourd\'hui ou dans le futur')
                                        ->addViolation();
                                }
                                
                                if ($value > $maxDate) {
                                    $context->buildViolation('La date ne peut pas être plus de 3 mois dans le futur')
                                        ->addViolation();
                                }
                            }
                        }
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

        // Add the data transformer to the tempsArrivage field
        $builder->get('tempsArrivage')
            ->addModelTransformer(new DateTimeToStringTransformer());
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => ReservationTransport::class,
        ]);
    }
}