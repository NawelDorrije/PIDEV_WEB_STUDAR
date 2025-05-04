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
use WebSocket\Client; // Ajoutez cette ligne
use App\Entity\Report;
use App\Form\ReportType;

class ChatController extends AbstractController
{
    #[Route('/chat', name: 'app_chat')]
    public function index(UtilisateurRepository $utilisateurRepository): Response
    {
        $currentUser = $this->getUser();
        if (!$currentUser instanceof Utilisateur) {
            throw $this->createNotFoundException('Utilisateur non connecté.');
        }
        $usersWithLastMessage = $utilisateurRepository->findAllWithLastMessage($currentUser);

        $users = $utilisateurRepository->findAll();
        return $this->render('chat/index.html.twig', [
            'current_user' => $currentUser,
            'users_with_last_message' => $usersWithLastMessage,
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
        $usersWithLastMessage = $utilisateurRepository->findAllWithLastMessage($currentUser);

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
            'users_with_last_message' => $usersWithLastMessage,

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

        // Log the MIME type and size for debugging
        error_log('Uploaded audio MIME type: ' . $audioFile->getMimeType());
        error_log('Uploaded audio size: ' . $audioFile->getSize() . ' bytes');

        // Validate file type and size (max 5MB)
        $allowedMimeTypes = [
            'audio/webm',
            'audio/webm;codecs=opus',
            'audio/mpeg',
            'audio/mp3',
            'audio/ogg',
            'audio/wav',
            'video/webm'
        ];
        if (!in_array($audioFile->getMimeType(), $allowedMimeTypes)) {
            return new JsonResponse([
                'success' => false,
                'error' => 'Type de fichier non supporté: ' . $audioFile->getMimeType()
            ], 400);
        }
        if ($audioFile->getSize() > 5 * 1024 * 1024) {
            return new JsonResponse(['success' => false, 'error' => 'Fichier trop volumineux (max 5MB).'], 400);
        }

        // Save temporarily
        $filename = uniqid('audio_') . '.' . $audioFile->guessExtension();
        $tempPath = $this->getParameter('audio_temp_dir') . '/' . $filename;

        try {
            $audioFile->move($this->getParameter('audio_temp_dir'), $filename);
            error_log('Audio file saved to: ' . $tempPath);
        } catch (\Exception $e) {
            error_log('Error saving audio file: ' . $e->getMessage());
            return new JsonResponse([
                'success' => false,
                'error' => 'Erreur lors de l\'enregistrement du fichier.',
                'details' => $e->getMessage()
            ], 500);
        }

        // Transcribe audio
        $transcription = '';
        try {
            $transcription = $chatbotService->transcribeAudio($tempPath);
            error_log('Transcription result: ' . $transcription);
        } catch (\Exception $e) {
            error_log('Transcription failed: ' . $e->getMessage());
            unlink($tempPath);
            return new JsonResponse([
                'success' => false,
                'error' => 'Transcription échouée: ' . $e->getMessage(),
                'details' => $e->getTraceAsString()
            ], 500);
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
        error_log('Message saved to database: ' . $message->getContent());

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
    
            error_log('Sending WebSocket message: ' . json_encode($messageData));
            try {
                if (!class_exists(Client::class)) {
                    error_log('WebSocket\Client class not found. Ensure textalk/websocket is installed.');
                    return;
                }
                $client = new Client('ws://localhost:8080');
                $client->send(json_encode($messageData));
                $client->close();
                error_log('WebSocket message sent successfully');
            } catch (\Exception $e) {
                error_log('WebSocket notification failed: ' . $e->getMessage());
            }
        }
        #[Route('/chat/report/{id}', name: 'app_chat_report', methods: ['GET', 'POST'])]
        public function report(Message $message, Request $request, EntityManagerInterface $entityManager): Response
        {
            $currentUser = $this->getUser();
            if (!$currentUser instanceof Utilisateur) {
                $this->addFlash('error', 'Vous devez être connecté pour signaler un message.');
                return $this->redirectToRoute('app_chat');
            }
        
            if (!$message || $message->getSenderCin()->getCin() === $currentUser->getCin()) {
                $this->addFlash('error', 'Message non trouvé ou non signalable.');
                return $this->redirectToRoute('app_chat_conversation', ['receiverCin' => $message->getSenderCin()->getCin()]);
            }
        
            $report = new Report();
            $report->setReportedBy($currentUser);
            $report->setMessage($message);
            $report->setCreatedAt(new \DateTime());
        
            $form = $this->createForm(ReportType::class, $report, [
                'csrf_token_id' => 'report_message',
            ]);
            $form->handleRequest($request);
        
            if ($form->isSubmitted()) {
                if ($form->isValid()) {
                    try {
                        $entityManager->persist($report);
                        $entityManager->flush();
                        $this->addFlash('success', 'Le message a été signalé avec succès.');
                        return $this->redirectToRoute('app_chat_conversation', ['receiverCin' => $message->getSenderCin()->getCin()]);
                    } catch (\Exception $e) {
                        error_log('Flush error: ' . $e->getMessage() . ' | Trace: ' . $e->getTraceAsString());
                        $this->addFlash('error', 'Erreur lors de l\'enregistrement : ' . $e->getMessage());
                    }
                } else {
                    $errors = $form->getErrors(true, true);
                    foreach ($errors as $error) {
                        error_log('Form error: ' . $error->getMessage() . ' | Field: ' . ($error->getOrigin() ? $error->getOrigin()->getName() : 'N/A'));
                        $this->addFlash('error', 'Erreur : ' . $error->getMessage());
                    }
                }
            }
        
            return $this->render('chat/report_form.html.twig', [
                'form' => $form->createView(),
                'message' => $message,
                'receiverCin' => $message->getSenderCin()->getCin(),
            ]);
        }
}