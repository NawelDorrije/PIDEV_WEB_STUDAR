<?php
// src/Form/CompleteRegistrationType.php
namespace App\Form;

use App\Entity\Utilisateur;
use App\Enums\RoleUtilisateur;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;

class CompleteRegistrationType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('cin', TextType::class, [
                'label' => 'CIN (8 chiffres)',
                'attr' => [
                    'pattern' => '\d{8}',
                    'title' => 'Le CIN doit contenir exactement 8 chiffres'
                ]
            ])
            ->add('role', ChoiceType::class, [
                'label' => 'Rôle',
                'choices' => [
                    'Étudiant' => RoleUtilisateur::ETUDIANT,
                    'Propriétaire' => RoleUtilisateur::PROPRIETAIRE,
                    'Transporteur' => RoleUtilisateur::TRANSPORTEUR
                ],
                'choice_label' => function (RoleUtilisateur $choice) {
                    return match ($choice) {
                        RoleUtilisateur::ETUDIANT => 'Étudiant',
                        RoleUtilisateur::PROPRIETAIRE => 'Propriétaire',
                        RoleUtilisateur::TRANSPORTEUR => 'Transporteur',
                        default => $choice->value,
                    };
                },
                'choice_value' => function (?RoleUtilisateur $choice) {
                    return $choice?->value;
                },
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Utilisateur::class,
        ]);
    }
}