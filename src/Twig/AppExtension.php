<?php

namespace App\Twig;

use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;



class AppExtension extends AbstractExtension
{
    private $parameterBag;
    private $logger;
    public function __construct(ParameterBagInterface $parameterBag, LoggerInterface $logger)
    {
        $this->parameterBag = $parameterBag;
        $this->logger = $logger;
    }

    public function getFilters(): array
    {
        return [
            new TwigFilter('isWithin24Hours', [$this, 'isWithin24Hours']),
            new TwigFilter('file_path', [$this, 'getFilePath']),
        ];
    }

    public function isWithin24Hours(int $timestamp): bool
    {
        $now = time(); // Current timestamp in seconds
        $diffInSeconds = $now - $timestamp;
        $diffInHours = $diffInSeconds / (60 * 60); // Convert to hours
        return $diffInHours <= 24;
    }
    public function getFilePath(string $relativePath): string
    {
        $assetsDir = $this->parameterBag->get('kernel.project_dir') . '/assets';
        $fullPath = realpath($assetsDir . '/' . $relativePath);
        if (!$fullPath) {
            $this->logger->error('File path not found for: ' . $assetsDir . '/' . $relativePath);
            return '';
        }
        $normalizedPath = str_replace('\\', '/', $fullPath);
        $this->logger->debug('Resolved file path: ' . $normalizedPath);
        return $normalizedPath;
    }
}






  

  

   

   
