<?php
// src/Controller/ChatbotController.php

namespace App\Controller;

use App\Service\ChatbotService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\{JsonResponse, Request, Response};
use Symfony\Component\Routing\Annotation\Route;

class ChatbotController extends AbstractController
{
    #[Route('/chat-ui', name: 'chat_ui')]
    public function chatUI(): Response
    {
        return $this->render('chatbot/index.html.twig');
    }

    #[Route('/chatia', name: 'app_chatia', methods: ['POST'])]
    public function chat(Request $request, ChatbotService $chatbotService): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        
        if (empty($data['message'])) {
            return $this->json(['error' => 'Message is required'], 400);
        }

        $botResponse = $chatbotService->getResponse($data['message']);

        return $this->json([
            'response' => $botResponse
        ]);
    }
}