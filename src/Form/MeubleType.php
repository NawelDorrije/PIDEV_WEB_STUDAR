<?php

namespace App\Form;

use App\Entity\GestionMeubles\Meuble;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\PositiveOrZero;
use Symfony\Component\Validator\Constraints\File;

class MeubleType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $isEdit = $options['data'] && $options['data']->getId(); // Vérifie si c'est une modification

        $builder
            ->add('nom', null, [
                'constraints' => [
                    new NotBlank(['message' => 'Le nom du meuble est obligatoire.']),
                    new Length([
                        'min' => 3,
                        'minMessage' => 'Le nom doit contenir au moins {{ limit }} caractères.',
                    ]),
                ],
            ])
            ->add('description', TextareaType::class, [
                'required' => false, // Rendre non obligatoire
                'constraints' => [
                    // Pas de NotBlank pour rendre le champ facultatif
                    new Length([
                        'min' => 10,
                        'minMessage' => 'La description doit contenir au moins {{ limit }} caractères.',
                        // La contrainte Length s'applique uniquement si le champ n'est pas vide
                        // Symfony ignore automatiquement la contrainte Length si la valeur est null ou une chaîne vide
                    ]),
                ],
            ])
            ->add('prix', NumberType::class, [
                'invalid_message' => 'Le prix doit être un nombre valide.',
                'constraints' => [
                    new NotBlank(['message' => 'Le prix est obligatoire.']),
                    new PositiveOrZero(['message' => 'Le prix doit être un nombre positif ou zéro.']),
                ],
            ])
            ->add('image', FileType::class, [
                'required' => !$isEdit, // Obligatoire uniquement pour l'ajout
                'mapped' => false, // Non mappé directement à l'entité
                'constraints' => $isEdit ? [] : [ // Pas de contraintes en mode édition si aucune image n'est uploadée
                    new File([
                        'maxSize' => '5M',
                        'mimeTypes' => ['image/jpeg', 'image/png', 'image/gif', 'image/webp'],
                        'mimeTypesMessage' => 'Veuillez uploader une image valide (JPEG, PNG, GIF, WEBP).',
                        'maxSizeMessage' => 'Le fichier est trop volumineux (max 5MB).',
                    ]),
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Meuble::class,
        ]);
    }
}