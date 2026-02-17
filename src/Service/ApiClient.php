<?php

declare(strict_types=1);

namespace AgencyPowerstack\ContaoBlogSyncBundle\Service;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Psr\Log\LoggerInterface;

class ApiClient
{
    private Client $client;
    private LoggerInterface $logger;
    private string $apiUrl;
    private ?string $accessToken = null;

    public function __construct(LoggerInterface $logger, string $apiUrl = 'https://app.agency-powerstack.com/backend/integrations/contao')
    {
        $this->logger = $logger;
        $this->apiUrl = rtrim($apiUrl, '/');
        $this->client = new Client([
            'base_uri' => $this->apiUrl,
            'timeout' => 30,
            'headers' => [
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
            ]
        ]);
    }

    /**
     * Authentifizierung bei der API
     */
    public function authenticate(string $clientId, string $clientSecret): bool
    {
        try {
            $response = $this->client->post('/login', [
                'json' => [
                    'client_id' => $clientId,
                    'client_secret' => $clientSecret,
                ]
            ]);

            $data = json_decode($response->getBody()->getContents(), true);
            
            if (isset($data['access_token'])) {
                $this->accessToken = $data['access_token'];
                $this->logger->info('API authentication successful');
                return true;
            }

            $this->logger->error('API authentication failed: No access token received');
            return false;

        } catch (GuzzleException $e) {
            $this->logger->error('API authentication error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Setzt Access Token manuell (z.B. aus Datenbank)
     */
    public function setAccessToken(string $token): void
    {
        $this->accessToken = $token;
    }

    /**
     * Holt neue Blogs von der API
     */
    public function fetchNewBlogs(?int $lastSyncTimestamp = null): array
    {
        if (!$this->accessToken) {
            $this->logger->error('Cannot fetch blogs: Not authenticated');
            return [];
        }

        try {
            $queryParams = [];
            if ($lastSyncTimestamp) {
                $queryParams['since'] = $lastSyncTimestamp;
            }

            $response = $this->client->get('/blogs', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->accessToken,
                ],
                'query' => $queryParams,
            ]);

            $data = json_decode($response->getBody()->getContents(), true);
            
            if (isset($data['blogs']) && is_array($data['blogs'])) {
                $this->logger->info('Fetched ' . count($data['blogs']) . ' blogs from API');
                return $data['blogs'];
            }

            $this->logger->warning('No blogs found in API response');
            return [];

        } catch (GuzzleException $e) {
            $this->logger->error('Error fetching blogs: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Bestätigt Import eines Blogs
     */
    public function confirmBlogImport(string $blogId): bool
    {
        if (!$this->accessToken) {
            return false;
        }

        try {
            $this->client->post("/blogs/{$blogId}/confirm", [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->accessToken,
                ]
            ]);

            return true;

        } catch (GuzzleException $e) {
            $this->logger->error("Error confirming blog import: " . $e->getMessage());
            return false;
        }
    }
}