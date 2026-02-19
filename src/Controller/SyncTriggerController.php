<?php

declare(strict_types=1);

namespace AgencyPowerstack\ContaoBlogSyncBundle\Controller;

use AgencyPowerstack\ContaoBlogSyncBundle\Service\ApiClient;
use AgencyPowerstack\ContaoBlogSyncBundle\Service\BlogImporter;
use Contao\CoreBundle\Framework\ContaoFramework;
use Doctrine\DBAL\Connection;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/contao/blog-sync/trigger-sync', name: 'blog_sync_trigger', methods: ['POST'])]
#[IsGranted('ROLE_USER')]
class SyncTriggerController extends AbstractController
{
    public function __construct(
        private readonly ApiClient $apiClient,
        private readonly BlogImporter $blogImporter,
        private readonly Connection $connection,
        private readonly ContaoFramework $framework,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function __invoke(Request $request): JsonResponse
    {
        try {
            $this->framework->initialize();

            $connectionId = $request->request->get('connection_id', '');

            $config = $this->loadConfig($connectionId);

            if (!$config) {
                return new JsonResponse(['success' => false, 'message' => 'Configuration not found'], 404);
            }

            if (!$config['sync_enabled']) {
                return new JsonResponse(['success' => false, 'message' => 'Sync is disabled'], 400);
            }

            // Authenticate
            if (!empty($config['access_token'])) {
                $this->apiClient->setAccessToken($config['access_token']);
            } else {
                if (!$this->apiClient->authenticate($config['client_id'], $config['client_secret'])) {
                    return new JsonResponse(['success' => false, 'message' => 'Authentication failed'], 401);
                }
            }

            // Fetch blogs
            $lastSync = (int) $config['last_sync'];
            $blogs = $this->apiClient->fetchNewBlogs($lastSync);

            if (empty($blogs)) {
                $this->updateLastSync((int) $config['id']);
                return new JsonResponse(['success' => true, 'message' => 'No new blogs', 'imported' => 0]);
            }

            // Import blogs
            $archiveId = (int) $config['news_archive_id'];
            $results = $this->blogImporter->importMultiple($blogs, $archiveId);

            // Confirm imports
            foreach ($results['imported_ids'] as $blogId) {
                if ($blogId) {
                    $this->apiClient->confirmBlogImport($blogId);
                }
            }

            $this->updateLastSync((int) $config['id']);

            $this->logger->info('Blog sync triggered via WebSocket push', [
                'success' => $results['success'],
                'failed' => $results['failed'],
            ]);

            return new JsonResponse([
                'success' => true,
                'imported' => $results['success'],
                'failed' => $results['failed'],
            ]);

        } catch (\Exception $e) {
            $this->logger->error('Trigger sync error: ' . $e->getMessage());
            return new JsonResponse(['success' => false, 'message' => 'Sync error'], 500);
        }
    }

    private function loadConfig(string $connectionId): ?array
    {
        try {
            if (!empty($connectionId)) {
                return $this->connection->fetchAssociative(
                    'SELECT * FROM tl_blog_sync_config WHERE connection_id = ?',
                    [$connectionId]
                ) ?: null;
            }

            // Fallback: first config
            return $this->connection->fetchAssociative(
                'SELECT * FROM tl_blog_sync_config LIMIT 1'
            ) ?: null;
        } catch (\Exception $e) {
            return null;
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
