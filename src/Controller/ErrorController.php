<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

class ErrorController extends AbstractController
{
    public function show(\Throwable $exception): Response
    {
        // Gérer les erreurs 404
        if ($exception instanceof HttpExceptionInterface && $exception->getStatusCode() === 404) {
            return $this->render('page404.html.twig', [], new Response('', 404));
        }

        // Gérer d'autres erreurs (optionnel)
        return $this->render('error.html.twig', [
            'exception' => $exception,
            'status_code' => $exception instanceof HttpExceptionInterface ? $exception->getStatusCode() : 500,
        ], new Response('', $exception instanceof HttpExceptionInterface ? $exception->getStatusCode() : 500));
    }
}