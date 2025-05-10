<?php

namespace App\Controller;

use Psr\Container\ContainerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Contracts\Service\ServiceSubscriberInterface;
class ErrorController implements ServiceSubscriberInterface
{
    private $container;

    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
    }

    public function show(\Throwable $exception): Response
    {
        // Gérer les erreurs 404
        if ($exception instanceof HttpExceptionInterface && $exception->getStatusCode() === 404) {
            return $this->render('page404.html.twig', [], new Response('', 404));
        }

        // Gérer d'autres erreurs
        return $this->render('error.html.twig', [
            'exception' => $exception,
            'status_code' => $exception instanceof HttpExceptionInterface ? $exception->getStatusCode() : 500,
        ], new Response('', $exception instanceof HttpExceptionInterface ? $exception->getStatusCode() : 500));
    }

    public static function getSubscribedServices(): array
    {
        return [
            'twig' => \Twig\Environment::class,
        ];
    }

    protected function render(string $view, array $parameters = [], Response $response = null): Response
    {
        return new Response(
            $this->container->get('twig')->render($view, $parameters),
            $response ? $response->getStatusCode() : 200
        );
    }
}