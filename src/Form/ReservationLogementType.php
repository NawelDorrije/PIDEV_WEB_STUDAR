<?php
namespace App\Form;

use App\Entity\ReservationLogement;
use App\Entity\Utilisateur;
use App\Repository\UtilisateurRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\NotNull;
use Symfony\Component\Validator\Constraints\GreaterThan;
use Symfony\Component\Validator\Constraints\Callback;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

class ReservationLogementType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('dateDebut', null, [
                'widget' => 'single_text',
                'required' => true,
                'constraints' => [
                    new NotBlank(['message' => 'La date de début est obligatoire']),
                    new GreaterThan([
                        'value' => 'today',
                        'message' => 'La date de début doit être dans le futur'
                    ])
                ]
            ])
            ->add('dateFin', null, [
                'widget' => 'single_text',
                'required' => true,
                'constraints' => [
                    new NotBlank(['message' => 'La date de fin est obligatoire']),
                    new Callback([$this, 'validateDates'])
                ]
            ])
            ->add('proprietaire', EntityType::class, [
                'class' => Utilisateur::class,
                'choice_label' => function(Utilisateur $user) {
                    return $user->getNom().' '.$user->getPrenom();
                },
                'query_builder' => function (UtilisateurRepository $er) {
                    return $er->createQueryBuilder('u')
                        ->where("u.role = 'propriétaire'")
                        ->orderBy('u.nom', 'ASC');
                },
                'attr' => ['class' => 'logement-input'],
                'label' => 'Propriétaire',
                'constraints' => [
                    new NotNull(['message' => 'Le propriétaire est obligatoire'])
                ]
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
                'attr' => ['class' => 'logement-input'],
                'label' => 'Étudiant',
                'constraints' => [
                    new NotNull(['message' => 'L\'étudiant est obligatoire'])
                ]
            ])
            ->add('idLogement', null, [
                'constraints' => [
                    new NotBlank(['message' => 'Le logement est obligatoire'])
                ]
            ]);
    }

    public function validateDates($value, ExecutionContextInterface $context)
    {
        $form = $context->getRoot();
        $data = $form->getData();
        
        if (!$data instanceof ReservationLogement) {
            return;
        }
        
        if ($data->getDateDebut() && $data->getDateFin() && $data->getDateDebut() >= $data->getDateFin()) {
            $context->buildViolation('La date de fin doit être après la date de début')
                   ->atPath('dateFin')
                   ->addViolation();
        }
        
        // Durée minimum de réservation (ex: 1 mois)
        if ($data->getDateDebut() && $data->getDateFin()) {
            $diff = $data->getDateDebut()->diff($data->getDateFin());
            if ($diff->days < 30) {
                $context->buildViolation('La durée minimum de réservation est de 30 jours')
                       ->atPath('dateFin')
                       ->addViolation();
            }
        }
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => ReservationLogement::class,
        ]);
    }
}