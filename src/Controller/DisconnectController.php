<?php

declare(strict_types=1);

namespace AgencyPowerstack\ContaoBlogSyncBundle\Controller;

use Doctrine\DBAL\Connection;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/contao/blog-sync/disconnect', name: 'blog_sync_disconnect', methods: ['POST'])]
#[IsGranted('ROLE_USER')]
class DisconnectController extends AbstractController
{
    public function __construct(
        private readonly Connection $connection,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function __invoke(Request $request): JsonResponse
    {
        $connectionId = $request->request->get('connection_id', '');

        if (empty($connectionId)) {
            return new JsonResponse(['success' => false, 'message' => 'Missing connection_id'], 400);
        }

        try {
            $deleted = $this->connection->delete('tl_blog_sync_config', [
                'connection_id' => $connectionId,
            ]);

            if ($deleted > 0) {
                $this->logger->info('Blog Sync: Config removed via disconnect push', [
                    'connection_id' => $connectionId,
                ]);

                return new JsonResponse(['success' => true]);
            }

            return new JsonResponse(['success' => false, 'message' => 'Config not found'], 404);

        } catch (\Exception $e) {
            $this->logger->error('Blog Sync: Error during disconnect - ' . $e->getMessage());

            return new JsonResponse(['success' => false, 'message' => 'Error'], 500);
        }
    }
}
