<?php

declare(strict_types=1);

namespace AgencyPowerstack\ContaoBlogSyncBundle\Controller\Api;

use AgencyPowerstack\ContaoBlogSyncBundle\Service\BlogImporter;
use AgencyPowerstack\ContaoBlogSyncBundle\Service\SyncLogger;
use Contao\CoreBundle\Framework\ContaoFramework;
use Doctrine\DBAL\Connection;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * Receives blog posts pushed from Agency Powerstack via HTTP POST.
 * Authentication: Authorization: Bearer {api_key}
 */
#[Route('/contao/blog-sync/api/push', name: 'blog_sync_api_push', methods: ['POST'])]
class BlogPushController extends AbstractController
{
    public function __construct(
        private readonly Connection $connection,
        private readonly BlogImporter $blogImporter,
        private readonly SyncLogger $syncLogger,
        private readonly ContaoFramework $framework,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function __invoke(Request $request): JsonResponse
    {
        // Authenticate via Bearer token
        $config = $this->authenticate($request);
        if ($config === null) {
            return new JsonResponse(['success' => false, 'message' => 'Unauthorized'], Response::HTTP_UNAUTHORIZED);
        }

        $archiveId = (int) $config['news_archive_id'];
        if ($archiveId === 0) {
            $this->syncLogger->log((int) $config['id'], 'webhook', 'error', 0, 0, 'Kein Nachrichtenarchiv konfiguriert');
            return new JsonResponse(['success' => false, 'message' => 'No news archive configured'], Response::HTTP_BAD_REQUEST);
        }

        // Parse request body
        $body = json_decode($request->getContent(), true);
        if (empty($body) || empty($body['blog'])) {
            return new JsonResponse(['success' => false, 'message' => 'Invalid request body'], Response::HTTP_BAD_REQUEST);
        }

        $connectionId = $body['connectionId'] ?? '';
        if ($connectionId !== $config['connection_id']) {
            $this->logger->warning('BlogSync Push: connectionId mismatch', [
                'expected' => $config['connection_id'],
                'received' => $connectionId,
            ]);
            return new JsonResponse(['success' => false, 'message' => 'Connection ID mismatch'], Response::HTTP_UNAUTHORIZED);
        }

        // Initialize Contao framework for model access
        $this->framework->initialize();

        $blogData = $body['blog'];

        $news = $this->blogImporter->importBlog($blogData, $archiveId);

        if ($news === null) {
            $this->syncLogger->log(
                (int) $config['id'],
                'webhook',
                'error',
                0,
                1,
                'Import fehlgeschlagen: ' . ($blogData['title'] ?? 'Unknown')
            );
            return new JsonResponse(['success' => false, 'message' => 'Import failed'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        $this->syncLogger->log(
            (int) $config['id'],
            'webhook',
            'success',
            1,
            0,
            'Push empfangen: ' . $news->headline,
            ['newsId' => $news->id, 'externalId' => $blogData['id'] ?? null]
        );

        $this->updateLastSync((int) $config['id']);

        return new JsonResponse(['success' => true, 'newsId' => (int) $news->id]);
    }

    /**
     * Validates the Bearer token and returns the matching config row, or null if invalid.
     */
    private function authenticate(Request $request): ?array
    {
        $authHeader = $request->headers->get('Authorization', '');
        if (!str_starts_with($authHeader, 'Bearer ')) {
            return null;
        }

        $providedKey = substr($authHeader, 7);
        if (empty($providedKey)) {
            return null;
        }

        try {
            $configs = $this->connection->fetchAllAssociative(
                "SELECT * FROM tl_blog_sync_config WHERE sync_enabled = '1' AND api_key != ''"
            );
        } catch (\Exception $e) {
            $this->logger->error('BlogSync Push: DB error during auth - ' . $e->getMessage());
            return null;
        }

        foreach ($configs as $config) {
            if (hash_equals($config['api_key'], $providedKey)) {
                return $config;
            }
        }

        return null;
    }

    private function updateLastSync(int $configId): void
    {
        try {
            $this->connection->update(
                'tl_blog_sync_config',
                ['last_sync' => time()],
                ['id' => $configId]
            );
        } catch (\Exception) {
            // Non-critical
        }
    }
}
