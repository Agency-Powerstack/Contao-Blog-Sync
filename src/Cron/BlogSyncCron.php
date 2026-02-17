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

            // Konfiguration laden
            $config = $this->loadConfig();
            
            if (!$config || !$config['sync_enabled']) {
                return;
            }

            // Prüfen ob Synchronisation fällig ist
            $lastSync = (int) $config['last_sync'];
            $interval = (int) $config['sync_interval'];
            
            if (time() - $lastSync < $interval) {
                return; // Noch nicht Zeit für Sync
            }

            $this->logger->info('Starting automatic blog synchronization');

            // Authentifizierung
            if (!empty($config['access_token'])) {
                $this->apiClient->setAccessToken($config['access_token']);
            } else {
                if (!$this->apiClient->authenticate($config['client_id'], $config['client_secret'])) {
                    $this->logger->error('Authentication failed during cron sync');
                    return;
                }
            }

            // Blogs abrufen
            $blogs = $this->apiClient->fetchNewBlogs($lastSync);
            
            if (empty($blogs)) {
                $this->logger->info('No new blogs to sync');
                $this->updateLastSync();
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

            // Letzten Sync-Zeitpunkt aktualisieren
            $this->updateLastSync();

        } catch (\Exception $e) {
            $this->logger->error('Cron sync error: ' . $e->getMessage());
        }
    }

    private function loadConfig(): ?array
    {
        try {
            $result = $this->connection->fetchAssociative(
                'SELECT * FROM tl_blog_sync_config WHERE id = 1'
            );
            
            return $result ?: null;
        } catch (\Exception $e) {
            return null;
        }
    }

    private function updateLastSync(): void
    {
        try {
            $this->connection->update(
                'tl_blog_sync_config',
                ['last_sync' => time()],
                ['id' => 1]
            );
        } catch (\Exception $e) {
            $this->logger->error('Failed to update last sync time: ' . $e->getMessage());
        }
    }
}