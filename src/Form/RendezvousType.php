<?php

// src/Form/RendezvousType.php
namespace App\Form;

use App\Entity\Rendezvous;
use App\Entity\Utilisateur;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\TimeType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use App\Repository\UtilisateurRepository;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\NotNull;
use Symfony\Component\Validator\Constraints\GreaterThanOrEqual;
use Symfony\Component\Validator\Constraints\LessThanOrEqual;
use Symfony\Component\Validator\Constraints\Callback;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

class RendezvousType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('date', DateType::class, [
                'widget' => 'single_text',
                'required' => true,
                'attr' => ['class' => 'rendezvous-input'],
                'constraints' => [
                    new NotBlank(['message' => 'La date est obligatoire']),
                    new GreaterThanOrEqual([
                        'value' => 'today',
                        'message' => 'La date doit être aujourd\'hui ou dans le futur'
                    ]),
                    new LessThanOrEqual([
                        'value' => '+3 months',
                        'message' => 'La date ne peut pas être plus de 3 mois dans le futur'
                    ])
                ]
            ])
            ->add('heure', TimeType::class, [
                'input' => 'string',
                'widget' => 'choice',
                'hours' => [8, 9, 10, 11, 12, 13, 14, 15, 16, 17, 18, 19],
                'minutes' => [0, 15, 30, 45],
                'attr' => [
                    'class' => 'rendezvous-input',
                    'aria-label' => 'Heure du rendez-vous'
                ],
                'label' => 'Heure',
                'with_minutes' => true,
                'html5' => false,
                'placeholder' => [
                    'hour' => 'Heure', 
                    'minute' => 'Minute'
                ],
                'constraints' => [
                    new NotBlank(['message' => 'L\'heure est obligatoire']),
                    new Callback([$this, 'validateHeure'])
                ]
            ])
            ->add('proprietaire', EntityType::class, [
                'class' => Utilisateur::class,
                'choice_label' => function(Utilisateur $user) {
                    return $user->getNom().' '.$user->getPrenom();
                },
                'query_builder' => function (UtilisateurRepository $er) {
                    return $er->createQueryBuilder('u')
                        ->where("u.role = 'propriétaire'")
                        ->orderBy('u.nom', 'ASC');
                },
                'attr' => ['class' => 'rendezvous-input proprietaire-select'],
                'label' => 'Propriétaire',
                'choice_value' => 'cin',
                'constraints' => [
                    new NotNull(['message' => 'Le propriétaire est obligatoire'])
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
                'attr' => ['class' => 'rendezvous-input'],
                'label' => 'Étudiant',
                'constraints' => [
                    new NotNull(['message' => 'L\'étudiant est obligatoire'])
                ]
            ])
            ->add('idLogement', HiddenType::class, [
                'attr' => ['class' => 'logement-id'],
                'constraints' => [
                    new NotBlank(['message' => 'Le logement est obligatoire'])
                ]
            ]);
    }

    public function validateHeure($value, ExecutionContextInterface $context)
    {
        $form = $context->getRoot();
        $data = $form->getData();
        
        if (!$data instanceof Rendezvous) {
            return;
        }
        
        $heure = $data->getHeure();
        if (!$heure) {
            return;
        }
        
        // Vérifier que l'heure est entre 8h et 19h
        $heureParts = explode(':', $heure);
        $hours = (int)$heureParts[0];
        
        if ($hours < 8 || $hours > 19) {
            $context->buildViolation('Les rendez-vous doivent être entre 8h et 19h')
                ->atPath('heure')
                ->addViolation();
        }
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Rendezvous::class,
        ]);
    }
}