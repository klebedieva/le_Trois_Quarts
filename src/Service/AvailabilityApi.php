<?php

namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;

class AvailabilityApi
{
    private string $baseUrl;
    private ?string $apiKey;

    public function __construct(private HttpClientInterface $httpClient)
    {
        // Read from environment to avoid string DI bindings
        $this->baseUrl = (string)($_ENV['AVAILABILITY_API_BASE_URL'] ?? getenv('AVAILABILITY_API_BASE_URL') ?: '');
        $this->apiKey = $_ENV['AVAILABILITY_API_KEY'] ?? getenv('AVAILABILITY_API_KEY') ?: null;
    }

    /**
     * Checks availability via external API.
     * Returns true if available, false otherwise.
     */
    public function check(\DateTimeInterface $date, string $time, int $guests): bool
    {
        if ($this->baseUrl === '') {
            // No external API configured → do NOT block reservations in dev
            return true;
        }
        $url = rtrim($this->baseUrl, '/').'/availability';
        $headers = [];
        if ($this->apiKey) {
            $headers['Authorization'] = 'Bearer '.$this->apiKey;
        }
        $response = $this->httpClient->request('GET', $url, [
            'headers' => $headers,
            'query' => [
                'date' => $date->format('Y-m-d'),
                'time' => $time,
                'guests' => $guests,
            ],
            'timeout' => 5.0,
        ]);

        if ($response->getStatusCode() >= 400) {
            return false;
        }
        $data = $response->toArray(false);
        return (bool)($data['available'] ?? false);
    }
}
