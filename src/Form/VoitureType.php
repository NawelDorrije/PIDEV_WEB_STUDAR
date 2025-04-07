<?php

namespace App\Form;

use App\Entity\Voiture;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class VoitureType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('model')
            ->add('numSerie')
            ->add('photo', FileType::class, [
                   'label' => 'Photo',
                     'mapped' => false,
                     'required' => false,
                     'constraints' => [
                         new Image([
                             'maxSize' => '5M',
                             'maxSizeMessage' => 'The image is too large ({{ size }} {{ suffix }}). Allowed maximum size is {{ limit }} {{ suffix }}.',
                             'mimeTypes' => [
                                 'image/jpeg',
                                 'image/png',
                                 'image/gif',
                             ],
                             'mimeTypesMessage' => 'Please upload a valid image (JPEG, PNG, GIF).',
                         ]),
                     ],
                     'attr' => [
                         'accept' => 'image/*',
                     ],
                 ])
            ->add('disponibilite');     
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Voiture::class,
        ]);
    }
}
