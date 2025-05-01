<?php

namespace App\Service;

use Dompdf\Dompdf;
use Dompdf\Options;
use Psr\Log\LoggerInterface;

class PdfGenerator
{
    private $logger;

    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    public function generatePdf(string $html, string $filename = 'document.pdf', string $orientation = 'portrait'): string
    {
        try {
            // Log the HTML input for debugging
            $this->logger->debug('PDF Generator: HTML input', ['html' => substr($html, 0, 500)]); // Limit log size

            // Configure Dompdf
            $options = new Options();
            $options->set('isHtml5ParserEnabled', true);
            $options->set('isRemoteEnabled', false);
            $options->set('isPhpEnabled', true);
            $options->set('defaultFont', 'Times-Roman');
            $options->set('dpi', 150);
            $options->set('isFontSubsettingEnabled', true);
            $options->set('chroot', realpath(''));
            $options->set('defaultMediaType', 'print');
            $options->set('enable_unicode', true);

            // Instantiate Dompdf
            $dompdf = new Dompdf($options);
            $dompdf->loadHtml(mb_convert_encoding($html, 'HTML-ENTITIES', 'UTF-8'));
            $dompdf->setPaper('A4', $orientation);
            $dompdf->render();

            // Get the PDF output
            $output = $dompdf->output();

            // Check if output is empty
            if (empty($output)) {
                $this->logger->error('PDF Generator: Empty PDF output generated');
                throw new \RuntimeException('Échec de la génération du PDF : contenu vide.');
            }

            // Save to a file
            $result = file_put_contents($filename, $output);
            if ($result === false) {
                $this->logger->error('PDF Generator: Failed to write PDF file', ['filename' => $filename]);
                throw new \RuntimeException('Échec de l\'écriture du fichier PDF : ' . $filename);
            }

            $this->logger->info('PDF Generator: PDF generated successfully', ['filename' => $filename]);
            return $filename;

        } catch (\Exception $e) {
            $this->logger->error('PDF Generator: Error generating PDF', [
                'error' => $e->getMessage(),
                'filename' => $filename,
            ]);
            throw new \RuntimeException('Erreur lors de la génération du PDF : ' . $e->getMessage());
        }
    }
}