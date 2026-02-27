<?php

declare(strict_types=1);

namespace AgencyPowerstack\ContaoBlogSyncBundle\Controller\Api;

use AgencyPowerstack\ContaoBlogSyncBundle\Service\ApiAuthenticator;
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
 *
 * Authentication: Authorization: Bearer {api_key}
 *
 * The endpoint is intentionally minimal: it validates the Bearer token, delegates
 * the actual import work to BlogImporter, logs the result and returns a JSON response.
 * No file handling or business logic lives here.
 */
#[Route('/contao/blog-sync/api/push', name: 'blog_sync_api_push', methods: ['POST'])]
final class BlogPushController extends AbstractController
{
    public function __construct(
        private readonly ApiAuthenticator $authenticator,
        private readonly Connection $connection,
        private readonly BlogImporter $blogImporter,
        private readonly SyncLogger $syncLogger,
        private readonly ContaoFramework $framework,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function __invoke(Request $request): JsonResponse
    {
        // Authenticate via Bearer token (only enabled connections are checked)
        $config = $this->authenticator->authenticate($request);
        if ($config === null) {
            return new JsonResponse(['success' => false, 'message' => 'Unauthorized'], Response::HTTP_UNAUTHORIZED);
        }

        $archiveId = (int) $config['news_archive_id'];
        if ($archiveId === 0) {
            $this->syncLogger->log((int) $config['id'], 'webhook', 'error', 0, 0, 'Kein Nachrichtenarchiv konfiguriert');
            return new JsonResponse(['success' => false, 'message' => 'No news archive configured'], Response::HTTP_BAD_REQUEST);
        }

        // Parse request body; use JSON_THROW_ON_ERROR for explicit error handling
        // and limit nesting depth to guard against deeply nested payloads.
        try {
            $body = json_decode($request->getContent(), true, 32, \JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return new JsonResponse(['success' => false, 'message' => 'Invalid JSON body'], Response::HTTP_BAD_REQUEST);
        }

        if (empty($body['blog']) || !is_array($body['blog'])) {
            return new JsonResponse(['success' => false, 'message' => 'Missing or invalid blog payload'], Response::HTTP_BAD_REQUEST);
        }

        // Defense-in-depth: verify the connection ID in the body matches the authenticated config.
        // The API key already uniquely identifies the connection; this additional check makes
        // misrouted requests (e.g. a client accidentally sending to the wrong Contao instance)
        // immediately visible in the logs.
        $connectionId = (string) ($body['connectionId'] ?? '');
        if ($connectionId !== $config['connection_id']) {
            $this->logger->warning('BlogSync Push: connectionId mismatch', [
                'expected' => $config['connection_id'],
                'received' => $connectionId,
            ]);
            return new JsonResponse(['success' => false, 'message' => 'Connection ID mismatch'], Response::HTTP_UNAUTHORIZED);
        }

        // Initialize Contao framework to make models and DBAFS available
        $this->framework->initialize();

        $blogData = $body['blog'];
        $news     = $this->blogImporter->importBlog($blogData, $archiveId);

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
     * Updates the last_sync timestamp for the given config record.
     * Non-critical: failure is silently logged and does not affect the response.
     */
    private function updateLastSync(int $configId): void
    {
        try {
            $this->connection->update(
                'tl_blog_sync_config',
                ['last_sync' => time()],
                ['id' => $configId]
            );
        } catch (\Exception) {
            // Non-critical – do not propagate
        }
    }
}
