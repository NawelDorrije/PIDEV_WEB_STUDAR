<?php

namespace App\Form;

use App\Entity\Transport;
use App\Enums\GestionTransport\TransportStatus;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class TransportType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
           
            ->add('idVoiture')
            ->add('status', ChoiceType::class, [
                 'choices' => array_combine(
                            array_map(fn($case) => $case->value, TransportStatus::cases()),
                            TransportStatus::cases()
                    ),
                'choice_value' => function (?TransportStatus $status) {
                        return $status?->value;
                    },
                'attr' => ['class' => 'form-select']
                ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Transport::class,
        ]);
    }
}
