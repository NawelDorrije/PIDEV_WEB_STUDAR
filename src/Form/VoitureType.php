<?php

namespace App\Form;

use App\Entity\Voiture;
use App\Entity\Utilisateur;
use App\Enums\GestionTransport\VoitureDisponibilite;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Doctrine\ORM\EntityRepository;
use Vich\UploaderBundle\Form\Type\VichImageType;


class VoitureType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('utilisateur', EntityType::class, [
                'class' => Utilisateur::class,
                'choice_label' => function ($utilisateur) {
                    if (!$utilisateur) {
                        return '';
                    }
                    return sprintf('%s - %s %s', 
                        $utilisateur->getCin(),
                        $utilisateur->getNom(),
                        $utilisateur->getPrenom()
                    );
                },
                'label' => 'CIN Utilisateur',
                'placeholder' => 'Sélectionner un utilisateur',
                'required' => true,
                'query_builder' => function (EntityRepository $er) {
                    return $er->createQueryBuilder('u')
                        ->orderBy('u.cin', 'ASC');
                }
            ])
            ->add('model')
            ->add('numSerie')
            ->add('photoFile', VichImageType::class, [
                'label' => 'Photo',
                'required' => false,
                'allow_delete' => false,
                'download_uri' => false,
                'image_uri' => true,
                'attr' => ['accept' => 'image/*']
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
