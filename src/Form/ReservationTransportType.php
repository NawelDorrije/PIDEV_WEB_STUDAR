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

class ReservationTransportType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('adresseDepart', TextType::class, [
                'attr' => ['class' => 'transport-input'],
                'label' => 'Adresse de départ'
            ])
            ->add('adresseDestination', TextType::class, [
                'attr' => ['class' => 'transport-input'],
                'label' => 'Adresse de destination'
            ])
            ->add('tempsArrivage', TextType::class, [
                'attr' => ['class' => 'transport-input'],
                'label' => 'Temps d\'arrivage',
                'required' => false
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