<?php

namespace App\Form\GestionTransport;

use App\Entity\GestionTransport\Voiture;
use App\Enums\GestionTransport\VoitureDisponibilite;
use App\Entity\Utilisateur;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Validator\Constraints\Regex;
use Vich\UploaderBundle\Form\Type\VichImageType;

class VoitureType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('model')
            ->add('numSerie', TextType::class, [
                'constraints' => [
                    new Regex([
                        'pattern' => '/^\d{1,3}TU\d{1,4}$/',
                        'message' => 'Le numéro de série doit être composé de 1 à 3 chiffres, suivi de "TU", puis de 1 à 4 chiffres.',
                    ]),
                ],
            ])
            ->add('imageFile', VichImageType::class, [
                'label' => 'Image de la voiture',
                'required' => false,
                'allow_delete' => true,
                'delete_label' => 'Supprimer l\'image',
                'download_uri' => false,
                'imagine_pattern' => 'squared_thumbnail_small'
            ])
            ->add('disponibilite', ChoiceType::class, [
                'choices' => array_combine(
                    array_map(fn($case) => $case->value, VoitureDisponibilite::cases()),
                    VoitureDisponibilite::cases()
                ),
                'choice_value' => function (?VoitureDisponibilite $disponibilite) {
                    return $disponibilite?->value;
                },
                'attr' => ['class' => 'form-select']
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Voiture::class,
        ]);
    }
}