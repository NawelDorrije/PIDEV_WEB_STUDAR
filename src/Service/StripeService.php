<?php

namespace App\Service;

use Stripe\Stripe;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

class StripeService
{
    private string $secretKey;

    public function __construct(ParameterBagInterface $params)
    {
        $this->secretKey = $params->get('STRIPE_SECRET_KEY');
        
        if (empty($this->secretKey)) {
            throw new \RuntimeException('STRIPE_SECRET_KEY is not configured');
        }
        
        Stripe::setApiKey($this->secretKey);
    }

    public function createInvoice(array $invoiceData): array
    {
        try {
            $stripeInvoice = \Stripe\Invoice::create([
                'description' => 'Transport #' . $invoiceData['transport_id'],
                'metadata' => [
                    'transport_id' => $invoiceData['transport_id'],
                    'student_cin' => $invoiceData['student_cin']
                ],  
                'lines' => [[
                    'amount' => $invoiceData['amount'] * 100, // Convert to cents
                    'currency' => strtolower($invoiceData['currency']),
                    'description' => $invoiceData['description'],
                    'quantity' => 1
                ]]
            ]);
            
            return [
                'id' => $stripeInvoice->id,
            'pdf_url' => $stripeInvoice->invoice_pdf, // Make sure this is included
            'amount' => $invoiceData['amount']
            ];
            
        } catch (\Exception $e) {
            throw new \RuntimeException('Stripe invoice error: ' . $e->getMessage());
        }
    }
}