<?php

declare(strict_types=1);

namespace AgencyPowerstack\ContaoBlogSyncBundle\EventListener;

use Contao\DataContainer;
use Doctrine\DBAL\Connection;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Notifies the Agency Powerstack backend when a connection is deleted in the Contao backend.
 *
 * Registered as a `delete_callback` on tl_blog_sync_config. The deletion itself is
 * performed by Contao; this listener only sends a best-effort HTTP DELETE notification
 * to the backend so it can clean up the corresponding connection record on its side.
 * If the notification fails the listener logs the error but does NOT prevent the
 * local record from being deleted.
 */
final class AccountDeleteListener
{
    public function __construct(
        private readonly Connection $connection,
        private readonly LoggerInterface $logger,
        private readonly HttpClientInterface $httpClient,
        private readonly string $frontendUrl,
    ) {
    }

    /**
     * Sends a DELETE request to the Agency Powerstack backend for the connection being removed.
     */
    public function onDelete(DataContainer $dc): void
    {
        if (!$dc->id) {
            return;
        }

        try {
            $config = $this->connection->fetchAssociative(
                'SELECT connection_id FROM tl_blog_sync_config WHERE id = ?',
                [(int) $dc->id]
            );

            if (!$config || empty($config['connection_id'])) {
                $this->logger->warning('Blog Sync: Cannot notify backend about deletion – no connection_id found', [
                    'config_id' => $dc->id,
                ]);
                return;
            }

            $apiUrl = rtrim($this->frontendUrl, '/') . '/backend/contao/connections/' . urlencode($config['connection_id']);

            $this->httpClient->request('DELETE', $apiUrl, ['timeout' => 10]);

            $this->logger->info('Blog Sync: Backend notified about account deletion', [
                'connection_id' => $config['connection_id'],
            ]);

        } catch (TransportExceptionInterface $e) {
            // Network errors must not prevent the local deletion
            $this->logger->error('Blog Sync: Error notifying backend about deletion – ' . $e->getMessage());
        } catch (\Exception $e) {
            $this->logger->error('Blog Sync: Error notifying backend about deletion – ' . $e->getMessage());
        }
    }
}
