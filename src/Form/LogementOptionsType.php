<?php

namespace App\Form;

use App\Entity\Logement;
use App\Entity\LogementOptions;
use App\Entity\Options;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class LogementOptionsType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('valeur')
            ->add('logement', EntityType::class, [
                'class' => Logement::class,
                'choice_label' => 'id',
            ])
            ->add('option', EntityType::class, [
                'class' => Options::class,
                'choice_label' => 'id',
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => LogementOptions::class,
        ]);
    }
}
