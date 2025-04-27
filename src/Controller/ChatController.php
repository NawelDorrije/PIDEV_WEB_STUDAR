<?php

namespace App\Controller;
use Symfony\Component\HttpFoundation\JsonResponse;
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
        $currentUser = $this->getUser();
        if (!$currentUser instanceof Utilisateur) {
            throw $this->createNotFoundException('Utilisateur non connecté.');
        }

        $users = $utilisateurRepository->findAll();
        return $this->render('chat/index.html.twig', [
            'current_user' => $currentUser,
            'users' => $users,
        ]);
    }

   
    #[Route('/chat/{receiverCin}', name: 'app_chat_conversation')]
    public function conversation(
        string $receiverCin,
        UtilisateurRepository $utilisateurRepository,
        MessageRepository $messageRepository,
        Request $request
    ): Response {
        $currentUser = $this->getUser();
        if (!$currentUser instanceof Utilisateur) {
            throw $this->createNotFoundException('Utilisateur non connecté.');
        }

        $receiver = $utilisateurRepository->findOneBy(['cin' => $receiverCin]);
        if (!$receiver) {
            throw $this->createNotFoundException('Utilisateur non trouvé.');
        }

        $limit = 20;
        $totalMessages = $messageRepository->countConversationMessages($currentUser->getCin(), $receiverCin);
        $offset = max(0, $totalMessages - $limit);
        $messages = $messageRepository->findConversationPaginated($currentUser->getCin(), $receiverCin, $limit, $offset);

        $users = $utilisateurRepository->findAll();

        return $this->render('chat/conversation.html.twig', [
            'current_user' => $currentUser,
            'receiver' => $receiver,
            'messages' => $messages,
            'users' => $users,
            'total_messages' => $totalMessages,
            'loaded_messages' => count($messages),
            'limit' => $limit,
            'offset' => $offset,
        ]);
    }
    #[Route('/chat/{receiverCin}/load-previous', name: 'app_chat_load_previous', methods: ['GET'])]
    public function loadPreviousMessages(
        string $receiverCin,
        Request $request,
        UtilisateurRepository $utilisateurRepository,
        MessageRepository $messageRepository
    ): Response {
        $currentUser = $this->getUser();
        if (!$currentUser instanceof Utilisateur) {
            return $this->json(['error' => 'Utilisateur non connecté'], 403);
        }

        $receiver = $utilisateurRepository->findOneBy(['cin' => $receiverCin]);
        if (!$receiver) {
            return $this->json(['error' => 'Utilisateur non trouvé'], 404);
        }

        $offset = (int) $request->query->get('offset', 0);
        $limit = (int) $request->query->get('limit', 20);

        $newOffset = max(0, $offset - $limit);
        $messages = $messageRepository->findConversationPaginated($currentUser->getCin(), $receiverCin, $limit, $newOffset);
        $totalMessages = $messageRepository->countConversationMessages($currentUser->getCin(), $receiverCin);

        $messagesData = [];
        foreach ($messages as $message) {
            $messagesData[] = [
                'content' => $message->getContent(),
                'timestamp' => $message->getTimestamp()->format('d/m/Y H:i'),
                'isSender' => $message->getSenderCin()->getCin() === $currentUser->getCin(),
            ];
        }

        return $this->json([
            'messages' => $messagesData,
            'total_messages' => $totalMessages,
            'new_offset' => $newOffset,
        ]);
    }

    // #[Route('/chat/send', name: 'app_chat_send', methods: ['POST'])]
    // public function sendMessage(Request $request, EntityManagerInterface $entityManager, UtilisateurRepository $utilisateurRepository): JsonResponse
    // {
    //     $currentUser = $this->getUser();
    //     if (!$currentUser instanceof Utilisateur) {
    //         error_log('User not authenticated for /chat/send'); // Add this for debugging
    //         return new JsonResponse(['success' => false, 'error' => 'Utilisateur non connecté.'], 403);
    //     }
    
    //     $contentType = $request->headers->get('Content-Type');
    //     if (str_contains($contentType, 'application/json')) {
    //         $data = json_decode($request->getContent(), true);
    //         $receiverCin = $data['receiverCin'] ?? null;
    //         $content = $data['content'] ?? null;
    //     } else {
    //         $receiverCin = $request->request->get('receiverCin');
    //         $content = $request->request->get('content');
    //     }
    
    //     if (!$receiverCin || !$content) {
    //         return new JsonResponse(['success' => false, 'error' => 'Paramètres manquants.'], 400);
    //     }
    
    //     $receiver = $utilisateurRepository->findOneBy(['cin' => $receiverCin]);
    //     if (!$receiver) {
    //         return new JsonResponse(['success' => false, 'error' => 'Destinataire non trouvé.'], 404);
    //     }
    
    //     $message = new Message();
    //     $message->setSenderCin($currentUser);
    //     $message->setReceiverCin($receiver);
    //     $message->setContent($content);
    //     $message->setTimestamp(new \DateTime());
    
    //     $entityManager->persist($message);
    //     $entityManager->flush();
    
    //     return new JsonResponse([
    //         'success' => true,
    //         'message' => [
    //             'content' => $message->getContent(),
    //             'timestamp' => $message->getTimestamp()->format('d/m/Y H:i'),
    //         ],
    //     ]);
    // }
}