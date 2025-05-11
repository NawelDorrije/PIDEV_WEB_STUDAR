<?php
namespace App\Controller;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\HttpKernel\Exception\HttpException;

class ProxyController
{
    public function proxyProcessDirections(Request $request): Response
    {
        $client = HttpClient::create();
        try {
            $response = $client->request('POST', 'http://localhost:5000/api/process-directions', [
                'headers' => $request->headers->all(),
                'body' => $request->getContent(),
            ]);

            return new Response(
                $response->getContent(),
                $response->getStatusCode(),
                $response->getHeaders()
            );
        } catch (\Exception $e) {
            throw new HttpException(500, 'Proxy request to Gemini failed: ' . $e->getMessage());
        }
    }

    public function proxyMercure(Request $request): Response
    {
        $client = HttpClient::create();
        try {
            $url = 'http://localhost:3000/.well-known/mercure' . ($request->getQueryString() ? '?' . $request->getQueryString() : '');
            $response = $client->request('GET', $url, [
                'headers' => $request->headers->all(),
            ]);

            $headers = $response->getHeaders();
            // Ensure Content-Type is set for EventSource compatibility
            $headers['Content-Type'] = ['text/event-stream'];

            return new Response(
                $response->getContent(false), // Stream content for EventSource
                $response->getStatusCode(),
                $headers
            );
        } catch (\Exception $e) {
            throw new HttpException(500, 'Proxy request to Mercure failed: ' . $e->getMessage());
        }
    }
}