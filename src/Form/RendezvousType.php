<?php

// src/Form/RendezvousType.php

namespace App\Form;

use App\Entity\Rendezvous;
use App\Entity\Utilisateur;
use App\Repository\UtilisateurRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\TimeType;
use Symfony\Component\Form\Extension\Core\Type\TextType;

class RendezvousType extends AbstractType
{
  // src/Form/RendezvousType.php

public function buildForm(FormBuilderInterface $builder, array $options): void
{
    $builder
        ->add('date', null, [
            'widget' => 'single_text',
            'required' => true,
        ])
        ->add('heure')
        ->add('proprietaire', EntityType::class, [
            'class' => Utilisateur::class,
            'choice_label' => function(Utilisateur $user) {
                return $user->getNom().' '.$user->getPrenom(); // Only show full name
            },
            'query_builder' => function (UtilisateurRepository $er) {
                return $er->createQueryBuilder('u')
                    ->where("u.role = 'propriétaire'")
                    ->orderBy('u.nom', 'ASC');
            },
            'attr' => ['class' => 'rendezvous-input'],
            'label' => 'Propriétaire',
        ])
        ->add('etudiant', EntityType::class, [
            'class' => Utilisateur::class,
            'choice_label' => function(Utilisateur $user) {
                return $user->getNom().' '.$user->getPrenom(); // Only show full name
            },
            'query_builder' => function (UtilisateurRepository $er) {
                return $er->createQueryBuilder('u')
                    ->where("u.role = 'étudiant'")
                    ->orderBy('u.nom', 'ASC');
            },
            'attr' => ['class' => 'rendezvous-input'],
            'label' => 'Étudiant',
        ])
        ->add('idLogement');
}

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Rendezvous::class,
        ]);
    }
}