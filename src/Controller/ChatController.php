<?php

namespace App\Controller;

use App\Entity\Utilisateur;
use App\Entity\Message;
use App\Repository\UtilisateurRepository;
use App\Repository\MessageRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class ChatController extends AbstractController
{
    #[Route('/chat', name: 'app_chat')]
    public function index(UtilisateurRepository $utilisateurRepository): Response
    {
        $currentUser = $utilisateurRepository->findOneBy(['cin' => '12345678']);
        if (!$currentUser instanceof Utilisateur) {
            throw $this->createNotFoundException('Utilisateur statique non trouvé.');
        }
    
        $users = $utilisateurRepository->findAll();
        // dump($currentUser, $users); die; // Debug the data
        return $this->render('chat/index.html.twig', [
            'current_user' => $currentUser,
            'users' => $users,
        ]);
    }

   #[Route('/chat/{receiverCin}', name: 'app_chat_conversation')]
public function conversation(
    string $receiverCin,
    UtilisateurRepository $utilisateurRepository,
    MessageRepository $messageRepository
): Response {
    $currentUser = $utilisateurRepository->findOneBy(['cin' => '12345678']);
    if (!$currentUser instanceof Utilisateur) {
        throw $this->createNotFoundException('Utilisateur statique non trouvé.');
    }

    $receiver = $utilisateurRepository->findOneBy(['cin' => $receiverCin]);
    if (!$receiver) {
        throw $this->createNotFoundException('Utilisateur non trouvé.');
    }

    $messages = $messageRepository->findConversation($currentUser->getCin(), $receiverCin);
    $users = $utilisateurRepository->findAll(); // Add this line

    return $this->render('chat/conversation.html.twig', [
        'current_user' => $currentUser,
        'receiver' => $receiver,
        'messages' => $messages,
        'users' => $users, // Add this line
    ]);
}
    #[Route('/chat/send', name: 'app_chat_send', methods: ['POST'])]
    public function sendMessage(
        Request $request,
        EntityManagerInterface $entityManager,
        UtilisateurRepository $utilisateurRepository
    ): Response {
        // Récupérer un utilisateur statique (par exemple, avec un CIN spécifique)
        $currentUser = $utilisateurRepository->findOneBy(['cin' => '12345678']); // Remplacez par un CIN existant dans votre base
        if (!$currentUser instanceof Utilisateur) {
            throw $this->createNotFoundException('Utilisateur statique non trouvé.');
        }

        // Récupérer les données du formulaire
        $receiverCin = $request->request->get('receiverCin');
        $content = $request->request->get('content');

        // Vérifier que les données sont présentes
        if (!$receiverCin || !$content) {
            return $this->json(['error' => 'Données manquantes'], 400);
        }

        // Récupérer le destinataire
        $receiver = $utilisateurRepository->findOneBy(['cin' => $receiverCin]);
        if (!$receiver) {
            return $this->json(['error' => 'Destinataire non trouvé'], 404);
        }

        // Créer un nouveau message
        $message = new Message();
        $message->setSenderCin($currentUser);
        $message->setReceiverCin($receiver);
        $message->setContent($content);
        $message->setTimestamp(new \DateTime());

        // Enregistrer le message
        $entityManager->persist($message);
        $entityManager->flush();

        return $this->json(['success' => 'Message envoyé']);
    }
}