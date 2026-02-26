<?php

declare(strict_types=1);

namespace AgencyPowerstack\ContaoBlogSyncBundle\EventListener;

use Contao\DataContainer;
use Doctrine\DBAL\Connection;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

class AccountDeleteListener
{
    public function __construct(
        private readonly Connection $connection,
        private readonly LoggerInterface $logger,
        private readonly ParameterBagInterface $params,
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

            $frontendUrl = $this->params->has('blog_sync.frontend_url')
                ? $this->params->get('blog_sync.frontend_url')
                : (getenv('BLOG_SYNC_FRONTEND_URL') ?: 'https://app.agency-powerstack.com');

            $apiUrl = rtrim($frontendUrl, '/') . '/backend/contao/connections/' . urlencode($config['connection_id']);

            $context = stream_context_create([
                'http' => [
                    'method'        => 'DELETE',
                    'timeout'       => 10,
                    'ignore_errors' => true,
                ],
            ]);

            @file_get_contents($apiUrl, false, $context);

            $this->logger->info('Blog Sync: Backend notified about account deletion', [
                'connection_id' => $config['connection_id'],
            ]);

        } catch (\Exception $e) {
            // Don't prevent deletion even if notification fails
            $this->logger->error('Blog Sync: Error notifying backend about deletion - ' . $e->getMessage());
        }
    }
}
