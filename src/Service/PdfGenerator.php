<?php

namespace App\Service;

use Dompdf\Dompdf;
use Dompdf\Options;

class PdfGenerator
{
    public function generatePdf(string $html, string $filename = 'document.pdf', string $orientation = 'portrait'): string
    {
        // Configure Dompdf
        $options = new Options();
        $options->set('isHtml5ParserEnabled', true);
        $options->set('isRemoteEnabled', false); // No external resources needed
        $options->set('isPhpEnabled', true); // Enable PHP for Twig
        $options->set('defaultFont', 'Times-Roman'); // Match template's serif font
        $options->set('dpi', 150); // Higher resolution for crisp rendering
        $options->set('isFontSubsettingEnabled', true); // Optimize font loading
        $options->set('chroot', realpath('')); // Restrict file access for security
        $options->set('defaultMediaType', 'print'); // Optimize for print
        $options->set('enable_unicode', true); // Ensure UTF-8 support

        // Instantiate Dompdf
        $dompdf = new Dompdf($options);
        $dompdf->loadHtml(mb_convert_encoding($html, 'HTML-ENTITIES', 'UTF-8')); // Ensure UTF-8 encoding
        $dompdf->setPaper('A4', $orientation);
        $dompdf->render();

        // Output the PDF as a string
        $output = $dompdf->output();

        // Save to a file
        file_put_contents($filename, $output);

        return $filename;
    }
}