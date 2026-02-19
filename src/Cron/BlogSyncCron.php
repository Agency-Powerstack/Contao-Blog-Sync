<?php

declare(strict_types=1);

namespace AgencyPowerstack\ContaoBlogSyncBundle\Cron;

use AgencyPowerstack\ContaoBlogSyncBundle\Service\ApiClient;
use AgencyPowerstack\ContaoBlogSyncBundle\Service\BlogImporter;
use Contao\CoreBundle\Framework\ContaoFramework;
use Doctrine\DBAL\Connection;
use Psr\Log\LoggerInterface;

class BlogSyncCron
{
    private const SYNC_INTERVAL = 43200; // 12 hours

    private ApiClient $apiClient;
    private BlogImporter $blogImporter;
    private Connection $connection;
    private ContaoFramework $framework;
    private LoggerInterface $logger;

    public function __construct(
        ApiClient $apiClient,
        BlogImporter $blogImporter,
        Connection $connection,
        ContaoFramework $framework,
        LoggerInterface $logger
    ) {
        $this->apiClient = $apiClient;
        $this->blogImporter = $blogImporter;
        $this->connection = $connection;
        $this->framework = $framework;
        $this->logger = $logger;
    }

    public function run(): void
    {
        try {
            $this->framework->initialize();

            // Alle aktiven Konfigurationen laden und synchronisieren
            $configs = $this->loadAllConfigs();

            foreach ($configs as $config) {
                $this->syncConfig($config);
            }

        } catch (\Exception $e) {
            $this->logger->error('Cron sync error: ' . $e->getMessage());
        }
    }

    private function syncConfig(array $config): void
    {
        $lastSync = (int) $config['last_sync'];

        if (time() - $lastSync < self::SYNC_INTERVAL) {
            return;
        }

        $this->logger->info('Starting automatic blog synchronization', [
            'config_id' => $config['id'],
        ]);

        // Authentifizierung
        if (!empty($config['access_token'])) {
            $this->apiClient->setAccessToken($config['access_token']);
        } else {
            if (!$this->apiClient->authenticate($config['client_id'], $config['client_secret'])) {
                $this->logger->error('Authentication failed during cron sync', [
                    'config_id' => $config['id'],
                ]);
                return;
            }
        }

        // Blogs abrufen
        $blogs = $this->apiClient->fetchNewBlogs($lastSync);

        if (empty($blogs)) {
            $this->logger->info('No new blogs to sync');
            $this->updateLastSync((int) $config['id']);
            return;
        }

        // Blogs importieren
        $archiveId = (int) $config['news_archive_id'];
        $results = $this->blogImporter->importMultiple($blogs, $archiveId);

        $this->logger->info(sprintf(
            'Cron sync completed: %d successful, %d failed',
            $results['success'],
            $results['failed']
        ));

        // Bestätigungen senden
        foreach ($results['imported_ids'] as $blogId) {
            if ($blogId) {
                $this->apiClient->confirmBlogImport($blogId);
            }
        }

        $this->updateLastSync((int) $config['id']);
    }

    private function loadAllConfigs(): array
    {
        try {
            return $this->connection->fetchAllAssociative(
                "SELECT * FROM tl_blog_sync_config WHERE sync_enabled = '1'"
            );
        } catch (\Exception $e) {
            return [];
        }
    }

    private function updateLastSync(int $configId): void
    {
        try {
            $this->connection->update(
                'tl_blog_sync_config',
                ['last_sync' => time()],
                ['id' => $configId]
            );
        } catch (\Exception $e) {
            $this->logger->error('Failed to update last sync time: ' . $e->getMessage());
        }
    }
}
