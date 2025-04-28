<?php

namespace App\Controller;

use Symfony\Component\HttpFoundation\JsonResponse;
use App\Entity\Utilisateur;
use App\Entity\Message;
use App\Repository\UtilisateurRepository;
use App\Repository\MessageRepository;
use App\Service\ChatbotService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\File\UploadedFile;
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

    #[Route('/chat/send-voice/{receiverCin}', name: 'app_chat_send_voice', methods: ['POST'])]
    public function sendVoiceMessage(
        string $receiverCin,
        Request $request,
        EntityManagerInterface $entityManager,
        UtilisateurRepository $utilisateurRepository,
        ChatbotService $chatbotService
    ): JsonResponse {
        $currentUser = $this->getUser();
        if (!$currentUser instanceof Utilisateur) {
            return new JsonResponse(['success' => false, 'error' => 'Utilisateur non connecté.'], 403);
        }

        $receiver = $utilisateurRepository->findOneBy(['cin' => $receiverCin]);
        if (!$receiver) {
            return new JsonResponse(['success' => false, 'error' => 'Destinataire non trouvé.'], 404);
        }

        /** @var UploadedFile|null $audioFile */
        $audioFile = $request->files->get('audio');
        if (!$audioFile) {
            return new JsonResponse(['success' => false, 'error' => 'Aucun fichier audio fourni.'], 400);
        }

        // Log the MIME type for debugging
        error_log('Uploaded audio MIME type: ' . $audioFile->getMimeType());

        // Validate file type and size (max 5MB)
// In ChatController.php, update the $allowedMimeTypes array
$allowedMimeTypes = ['audio/webm', 'audio/mpeg', 'audio/mp3', 'audio/ogg', 'audio/wav', 'audio/webm;codecs=opus', 'video/webm'];        if (!in_array($audioFile->getMimeType(), $allowedMimeTypes)) {
            return new JsonResponse(['success' => false, 'error' => 'Type de fichier non supporté: ' . $audioFile->getMimeType()], 400);
        }
        if ($audioFile->getSize() > 5 * 1024 * 1024) {
            return new JsonResponse(['success' => false, 'error' => 'Fichier trop volumineux (max 5MB).'], 400);
        }

        // Save temporarily
        $filename = uniqid('audio_') . '.' . $audioFile->guessExtension();
        $tempPath = $this->getParameter('audio_temp_dir') . '/' . $filename;

        try {
            $audioFile->move($this->getParameter('audio_temp_dir'), $filename);
        } catch (\Exception $e) {
            return new JsonResponse(['success' => false, 'error' => 'Erreur lors de l\'enregistrement du fichier.'], 500);
        }

        // Transcribe audio
        $transcription = '';
        try {
            $transcription = $chatbotService->transcribeAudio($tempPath);
        } catch (\Exception $e) {
            unlink($tempPath);
            return new JsonResponse(['success' => false, 'error' => 'Transcription échouée: ' . $e->getMessage()], 500);
        }

        // Clean up
        unlink($tempPath);

        if (empty($transcription)) {
            return new JsonResponse(['success' => false, 'error' => 'Transcription vide.'], 400);
        }

        // Create Message entity
        $message = new Message();
        $message->setSenderCin($currentUser);
        $message->setReceiverCin($receiver);
        $message->setContent($transcription);
        $message->setTimestamp(new \DateTime());

        $entityManager->persist($message);
        $entityManager->flush();

        // Notify via WebSocket
        try {
            $this->notifyViaWebSocket($currentUser, $receiver, $message);
        } catch (\Exception $e) {
            error_log('WebSocket notification failed: ' . $e->getMessage());
        }

        return new JsonResponse([
            'success' => true,
            'message' => [
                'content' => $message->getContent(),
                'timestamp' => $message->getTimestamp()->format('d/m/Y H:i'),
            ],
        ]);
    }

    /**
     * Notify recipient via WebSocket
     */
    private function notifyViaWebSocket(Utilisateur $sender, Utilisateur $receiver, Message $message): void
    {
        $messageData = [
            'senderCin' => $sender->getCin(),
            'receiverCin' => $receiver->getCin(),
            'content' => $message->getContent(),
            'timestamp' => $message->getTimestamp()->format('c'),
        ];

        // Replace with your Ratchet integration
        $client = new \WebSocket\Client('ws://localhost:8080');
        $client->send(json_encode($messageData));
        $client->close();
    }
}