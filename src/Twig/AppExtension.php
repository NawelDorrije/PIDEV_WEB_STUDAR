<?php

namespace App\Twig;

use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

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
            new TwigFilter('file_path', [$this, 'getFilePath']),
        ];
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