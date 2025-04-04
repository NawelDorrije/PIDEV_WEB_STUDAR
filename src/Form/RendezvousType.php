<?php

namespace App\Form;

use App\Entity\Rendezvous;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class RendezvousType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
        ->add('date', null, [
          'widget' => 'single_text',
          'required' => true,
      ])
      ->add('heure', null, [
          'required' => true,
      ])
            ->add('cinProprietaire')
            ->add('cinEtudiant')
            ->add('idLogement')
            
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Rendezvous::class,
        ]);
    }
}
