<?php

declare(strict_types=1);

namespace AgencyPowerstack\ContaoBlogSyncBundle\Service;

use Doctrine\DBAL\Connection;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Validates Bearer token API keys for incoming push and disconnect requests.
 *
 * Extracts the Bearer token from the Authorization header, fetches all enabled
 * configurations from the database and performs a constant-time comparison
 * (hash_equals) to prevent timing attacks. Returns the matching config row
 * or null on failure.
 *
 * Only the columns required for authentication and routing are fetched
 * (no SELECT *) to minimise data exposure in memory.
 */
final class ApiAuthenticator
{
    public function __construct(
        private readonly Connection $connection,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * Authenticates an incoming request via Bearer token.
     *
     * @param Request $request         The incoming HTTP request.
     * @param bool    $requireEnabled  When true (default), only configs with sync_enabled=1 are checked.
     *                                 Pass false for endpoints that should still work after disabling sync
     *                                 (e.g. disconnect).
     *
     * @return array{id: int, api_key: string, connection_id: string, news_archive_id: int, sync_enabled: string}|null
     *         The matching config row, or null if authentication fails.
     */
    public function authenticate(Request $request, bool $requireEnabled = true): ?array
    {
        $authHeader = $request->headers->get('Authorization', '');
        if (!str_starts_with($authHeader, 'Bearer ')) {
            return null;
        }

        $providedKey = substr($authHeader, 7);
        if ($providedKey === '') {
            return null;
        }

        $where = "api_key != ''";
        if ($requireEnabled) {
            $where .= " AND sync_enabled = '1'";
        }

        try {
            $configs = $this->connection->fetchAllAssociative(
                "SELECT id, api_key, connection_id, news_archive_id, sync_enabled
                 FROM tl_blog_sync_config
                 WHERE {$where}"
            );
        } catch (\Exception $e) {
            $this->logger->error('BlogSync: DB error during authentication – ' . $e->getMessage());
            return null;
        }

        foreach ($configs as $config) {
            if (hash_equals($config['api_key'], $providedKey)) {
                return $config;
            }
        }

        return null;
    }
}
