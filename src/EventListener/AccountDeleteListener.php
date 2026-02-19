<?php

declare(strict_types=1);

namespace AgencyPowerstack\ContaoBlogSyncBundle\EventListener;

use AgencyPowerstack\ContaoBlogSyncBundle\Service\ApiClient;
use Contao\DataContainer;
use Doctrine\DBAL\Connection;
use Psr\Log\LoggerInterface;

class AccountDeleteListener
{
    public function __construct(
        private readonly ApiClient $apiClient,
        private readonly Connection $connection,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function onDelete(DataContainer $dc): void
    {
        if (!$dc->id) {
            return;
        }

        try {
            $config = $this->connection->fetchAssociative(
                'SELECT * FROM tl_blog_sync_config WHERE id = ?',
                [(int) $dc->id]
            );

            if (!$config || empty($config['connection_id'])) {
                $this->logger->warning('Blog Sync: Cannot notify backend about deletion - no connection_id found', [
                    'config_id' => $dc->id,
                ]);
                return;
            }

            // Authenticate
            if (!empty($config['access_token'])) {
                $this->apiClient->setAccessToken($config['access_token']);
            } else {
                $this->apiClient->authenticate($config['client_id'], $config['client_secret']);
            }

            // Notify backend about disconnection
            $result = $this->apiClient->deleteConnection($config['connection_id']);

            if ($result) {
                $this->logger->info('Blog Sync: Backend notified about account deletion', [
                    'connection_id' => $config['connection_id'],
                ]);
            } else {
                $this->logger->warning('Blog Sync: Failed to notify backend about account deletion', [
                    'connection_id' => $config['connection_id'],
                ]);
            }

        } catch (\Exception $e) {
            // Don't prevent deletion even if notification fails
            $this->logger->error('Blog Sync: Error notifying backend about deletion - ' . $e->getMessage());
        }
    }
}
