<?php

declare(strict_types=1);

namespace AgencyPowerstack\ContaoBlogSyncBundle\Controller;

use AgencyPowerstack\ContaoBlogSyncBundle\Service\ApiAuthenticator;
use Doctrine\DBAL\Connection;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * Handles disconnect requests pushed from Agency Powerstack via HTTP POST.
 *
 * Authentication: Authorization: Bearer {api_key}
 *
 * On a valid request the corresponding tl_blog_sync_config row is deleted.
 * The sync_enabled flag is intentionally NOT checked during authentication so
 * that a previously disabled connection can still be cleanly removed.
 */
#[Route('/contao/blog-sync/disconnect', name: 'blog_sync_disconnect', methods: ['POST'])]
final class DisconnectController extends AbstractController
{
    public function __construct(
        private readonly ApiAuthenticator $authenticator,
        private readonly Connection $connection,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function __invoke(Request $request): JsonResponse
    {
        // Pass requireEnabled=false so disabled connections can still be disconnected
        $config = $this->authenticator->authenticate($request, requireEnabled: false);
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
            $this->logger->error('Blog Sync: Error during disconnect – ' . $e->getMessage());

            return new JsonResponse(['success' => false, 'message' => 'Error'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
