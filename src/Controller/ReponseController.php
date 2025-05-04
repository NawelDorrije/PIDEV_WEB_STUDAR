<?php

namespace App\Controller;

use App\Entity\Reclamation;
use App\Entity\Reponse;
use App\Entity\Utilisateur;
use App\Form\ReponseType;
use App\Repository\ReclamationRepository;
use App\Repository\ReponseRepository;
use App\Service\ChatbotService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Notifier\TexterInterface;
use Symfony\Component\Notifier\Message\SmsMessage;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\Security;

#[Route('/admin/reclamation')]
class ReponseController extends AbstractController
{
    private $security;

    public function __construct(Security $security)
    {
        $this->security = $security;
    }

    #[Route('/{id}/respond', name: 'admin_reclamation_respond', methods: ['GET', 'POST'])]
    public function respond(
        Request $request,
        Reclamation $reclamation,
        EntityManagerInterface $entityManager,
        ReponseRepository $reponseRepository,
        ChatbotService $chatbotService,
        MailerInterface $mailer,
        TexterInterface $texter
    ): Response {
        // Récupérer l'utilisateur connecté (admin)
        $admin = $this->security->getUser();
        if (!$admin instanceof Utilisateur) {
            throw $this->createAccessDeniedException('Vous devez être connecté en tant qu\'admin.');
        }

        $reponse = new Reponse();
        $reponse->setReclamation($reclamation);
        $reponse->setAdmin($admin);
        $reponse->setTimestamp(new \DateTime());

        // Générer une suggestion de réponse via l'IA
        $prompt = sprintf(
            "Vous êtes un assistant qui aide un administrateur d'une plateforme de logement à répondre à une réclamation. Voici la réclamation : \nTitre : %s\nDescription : %s\nProposez une réponse professionnelle courte et adaptée. ne reecri pas la reclamation juste donne moi la reponse direct ne me laisse pas la places des noms et donne moi briefement la reponse.",
            $reclamation->getTitre(),
            $reclamation->getDescription()
        );
        $suggestedResponse = $chatbotService->getResponse($prompt);
        $reponse->setContenueReponse($suggestedResponse);

        $form = $this->createForm(ReponseType::class, $reponse);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Mettre à jour le statut de la réclamation à "traité"
            $reclamation->setStatut('traité'); 
            // Persister la réponse et la réclamation mise à jour
            $entityManager->persist($reponse);
            $entityManager->persist($reclamation);
            $entityManager->flush();

            // Récupérer l'utilisateur (auteur de la réclamation)
            $user = $reclamation->getUtilisateur();
            $userEmail = $user ? $user->getEmail() : null;
            $userPhone = $user ? $user->getNumTel() : null;

            // Récupérer le propriétaire du logement
            $logement = $reclamation->getLogement();
            $proprietaire = $logement ? $logement->getUtilisateurCin() : null;
            $proprietaireEmail = $proprietaire ? $proprietaire->getEmail() : null;
            $proprietairePhone = $proprietaire ? $proprietaire->getNumTel() : null;

            // Envoyer un email à l'utilisateur
            if ($userEmail && filter_var($userEmail, FILTER_VALIDATE_EMAIL)) {
                $emailToUser = (new Email())
                    ->from('no-reply@yourdomain.com')
                    ->to($userEmail)
                    ->subject('Réponse à votre réclamation #' . $reclamation->getId())
                    ->html($this->renderView('emails/reclamation_response_user.html.twig', [
                        'reclamation' => $reclamation,
                        'reponse' => $reponse,
                        'user' => $user,
                    ]));

                try {
                    $mailer->send($emailToUser);
                } catch (\Exception $e) {
                    error_log('Failed to send email to user: ' . $e->getMessage());
                    $this->addFlash('warning', 'La réponse a été enregistrée, mais l\'email à l\'utilisateur n\'a pas pu être envoyé.');
                }
            } else {
                error_log('Invalid or missing email address for user: ' . ($user ? $user->getCin() : 'Unknown'));
            }

            // Envoyer un SMS à l'utilisateur
            if ($userPhone) {
                $smsToUser = new SmsMessage(
                    $userPhone,
                    'Votre réclamation #' . $reclamation->getId() . ' a été traitée. Consultez votre email pour plus de détails.'
                );
                $smsToUser->transport('infobip');

                try {
                    $texter->send($smsToUser);
                } catch (\Exception $e) {
                    error_log('Failed to send SMS to user: ' . $e->getMessage());
                    $this->addFlash('warning', 'La réponse a été enregistrée, mais le SMS à l\'utilisateur n\'a pas pu être envoyé.');
                }
            } else {
                error_log('Missing phone number for user: ' . ($user ? $user->getCin() : 'Unknown'));
            }

            // Générer un message pour le propriétaire via l'IA
            if ($proprietaireEmail && filter_var($proprietaireEmail, FILTER_VALIDATE_EMAIL)) {
                $promptForProprietaire = sprintf(
                    "Vous êtes un assistant qui rédige une notification pour le propriétaire d'un logement sur une plateforme de gestion. Une réclamation a été traitée pour son logement. Voici les détails : \nTitre de la réclamation : %s\nDescription : %s\nRéponse de l'administrateur : %s\nRédigez une notification professionnelle et concise pour informer le propriétaire, en lui demandant de prendre les mesures nécessaires si besoin. Ne répétez pas les détails de la réclamation ou de la réponse dans le message, juste donnez une notification directe.",
                    $reclamation->getTitre(),
                    $reclamation->getDescription(),
                    $reponse->getContenueReponse()
                );
                $messageForProprietaire = $chatbotService->getResponse($promptForProprietaire);

                if (str_starts_with($messageForProprietaire, 'Error:')) {
                    $messageForProprietaire = 'Une réclamation concernant votre logement a été traitée. Veuillez prendre les mesures nécessaires si besoin.';
                }

                $emailToProprietaire = (new Email())
                    ->from('no-reply@yourdomain.com')
                    ->to($proprietaireEmail)
                    ->subject('Nouvelle réponse à une réclamation concernant votre logement #' . $logement->getId())
                    ->html($this->renderView('emails/reclamation_response_proprietaire.html.twig', [
                        'reclamation' => $reclamation,
                        'reponse' => $reponse,
                        'logement' => $logement,
                        'proprietaire' => $proprietaire,
                        'messageForProprietaire' => $messageForProprietaire,
                    ]));

                try {
                    $mailer->send($emailToProprietaire);
                } catch (\Exception $e) {
                    error_log('Failed to send email to proprietaire: ' . $e->getMessage());
                    $this->addFlash('warning', 'La réponse a été enregistrée, mais l\'email au propriétaire n\'a pas pu être envoyé.');
                }
            } else {
                error_log('Invalid or missing email address for proprietaire: ' . ($proprietaire ? $proprietaire->getCin() : 'Unknown'));
            }

            // Envoyer un SMS au propriétaire
            if ($proprietairePhone) {
                $smsToProprietaire = new SmsMessage(
                    $proprietairePhone,
                    'Une réclamation pour votre logement #' . $logement->getId() . ' a été traitée. Consultez votre email pour plus de détails.'
                );
                $smsToProprietaire->transport('infobip');

                try {
                    $texter->send($smsToProprietaire);
                } catch (\Exception $e) {
                    error_log('Failed to send SMS to proprietaire: ' . $e->getMessage());
                    $this->addFlash('warning', 'La réponse a été enregistrée, mais le SMS au propriétaire n\'a pas pu être envoyé.');
                }
            } else {
                error_log('Missing phone number for proprietaire: ' . ($proprietaire ? $proprietaire->getCin() : 'Unknown'));
            }

            $this->addFlash('success', 'La réponse a été envoyée avec succès et la réclamation a été marquée comme traitée.');

            return $this->redirectToRoute('admin_reclamation_show', ['id' => $reclamation->getId()]);
        }

        return $this->render('admin/reclamation/respond.html.twig', [
            'reclamation' => $reclamation,
            'form' => $form->createView(),
            'suggestedResponse' => $suggestedResponse,
        ]);
    }
}