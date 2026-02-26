<?php

declare(strict_types=1);

namespace AgencyPowerstack\ContaoBlogSyncBundle\Controller;

use Doctrine\DBAL\Connection;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * Receives disconnect requests from Agency Powerstack via HTTP POST.
 * Authentication: Authorization: Bearer {api_key}
 */
#[Route('/contao/blog-sync/disconnect', name: 'blog_sync_disconnect', methods: ['POST'])]
class DisconnectController extends AbstractController
{
    public function __construct(
        private readonly Connection $connection,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function __invoke(Request $request): JsonResponse
    {
        $config = $this->authenticate($request);
        if ($config === null) {
            return new JsonResponse(['success' => false, 'message' => 'Unauthorized'], Response::HTTP_UNAUTHORIZED);
        }

        try {
            $this->connection->delete('tl_blog_sync_config', ['id' => (int) $config['id']]);

            $this->logger->info('Blog Sync: Config removed via disconnect push', [
                'connection_id' => $config['connection_id'],
            ]);

            return new JsonResponse(['success' => true]);

        } catch (\Exception $e) {
            $this->logger->error('Blog Sync: Error during disconnect - ' . $e->getMessage());

            return new JsonResponse(['success' => false, 'message' => 'Error'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

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
                "SELECT * FROM tl_blog_sync_config WHERE api_key != ''"
            );
        } catch (\Exception $e) {
            $this->logger->error('Blog Sync Disconnect: DB error during auth - ' . $e->getMessage());
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
