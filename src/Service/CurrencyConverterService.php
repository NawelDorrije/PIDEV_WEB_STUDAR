<?php

namespace App\Service;

use Psr\Log\LoggerInterface;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\Exception\HttpExceptionInterface;

class CurrencyConverterService
{
    private string $apiKey;
    private HttpClientInterface $httpClient;
    private LoggerInterface $logger;
    private FilesystemAdapter $cache;
    private const FALLBACK_EXCHANGE_RATE = 0.30; // 1 TND = 0.30 EUR
    private const CACHE_KEY = 'exchange_rate_tnd_eur';
    private const CACHE_TTL = 3600; // Cache pendant 1 heure

    public function __construct(string $exchangeRateApiKey, HttpClientInterface $httpClient, LoggerInterface $logger)
    {
        $this->apiKey = $exchangeRateApiKey;
        $this->httpClient = $httpClient;
        $this->logger = $logger;
        $this->cache = new FilesystemAdapter();
        $this->logger->info('CurrencyConverterService initialized with API key', ['apiKey' => substr($exchangeRateApiKey, 0, 4) . '****']);
    }

    public function getExchangeRate(): float
    {
        // Vérifier le cache
        $cacheItem = $this->cache->getItem(self::CACHE_KEY);
        if ($cacheItem->isHit()) {
            $exchangeRate = $cacheItem->get();
            $this->logger->info('Using cached exchange rate', ['1_TND' => $exchangeRate . ' EUR']);
            return $exchangeRate;
        }

        $apiUrl = "https://api.exchangeratesapi.io/v1/latest?access_key={$this->apiKey}&symbols=EUR,TND";

        try {
            $this->logger->info('Fetching exchange rate from API', ['url' => $apiUrl]);
            $response = $this->httpClient->request('GET', $apiUrl);
            $data = $response->toArray();

            if (!$data['success']) {
                $this->logger->error('Exchange rate API error', [
                    'error' => $data['error']['info'] ?? 'Unknown error',
                    'code' => $data['error']['code'] ?? null,
                ]);
                return $this->useFallbackRate();
            }

            if (!isset($data['rates']['EUR'], $data['rates']['TND'])) {
                $this->logger->error('Missing EUR or TND rates in API response', ['response' => $data]);
                return $this->useFallbackRate();
            }

            $eurRate = (float) $data['rates']['EUR'];
            $tndRate = (float) $data['rates']['TND'];

            if ($tndRate <= 0 || $eurRate <= 0) {
                $this->logger->error('Invalid exchange rates', [
                    'EUR' => $eurRate,
                    'TND' => $tndRate,
                ]);
                return $this->useFallbackRate();
            }

            $exchangeRate = $eurRate / $tndRate;
            if ($exchangeRate <= 0) {
                $this->logger->error('Calculated exchange rate is invalid', ['exchangeRate' => $exchangeRate]);
                return $this->useFallbackRate();
            }

            // Mettre en cache le taux
            $cacheItem->set($exchangeRate);
            $cacheItem->expiresAfter(self::CACHE_TTL);
            $this->cache->save($cacheItem);

            $this->logger->info('Fetched exchange rate', [
                '1_TND' => $exchangeRate . ' EUR',
                'timestamp' => $data['timestamp'] ?? null,
                'date' => $data['date'] ?? null,
            ]);
            return $exchangeRate;
        } catch (HttpExceptionInterface $e) {
            $this->logger->error('HTTP error while fetching exchange rate', [
                'message' => $e->getMessage(),
                'code' => $e->getCode(),
            ]);
            return $this->useFallbackRate();
        } catch (\Exception $e) {
            $this->logger->error('Unexpected error while fetching exchange rate', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return $this->useFallbackRate();
        }
    }

    private function useFallbackRate(): float
    {
        $this->logger->info('Using fallback exchange rate', ['rate' => self::FALLBACK_EXCHANGE_RATE]);
        return self::FALLBACK_EXCHANGE_RATE;
    }

    public function convertTndToEur(float $amountInTnd): float
    {
        if (!is_numeric($amountInTnd) || $amountInTnd <= 0) {
            $this->logger->error('Invalid amount for conversion', ['amount' => $amountInTnd]);
            return -1;
        }

        $exchangeRate = $this->getExchangeRate();
        $amountInEur = $amountInTnd * $exchangeRate;

        if ($amountInEur <= 0) {
            $this->logger->error('Invalid converted amount', [
                'amountInTnd' => $amountInTnd,
                'exchangeRate' => $exchangeRate,
                'amountInEur' => $amountInEur,
            ]);
            return -1;
        }

        $this->logger->info('Converted amount', [
            'tnd' => $amountInTnd,
            'eur' => $amountInEur,
            'exchangeRate' => $exchangeRate,
        ]);
        return $amountInEur;
    }
}