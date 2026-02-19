<?php

declare(strict_types=1);

namespace AgencyPowerstack\ContaoBlogSyncBundle\Controller;

use Doctrine\DBAL\Connection;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/contao/blog-sync/callback', name: 'blog_sync_callback')]
class ConnectCallbackController extends AbstractController
{
    public function __construct(
        private readonly Connection $connection,
        private readonly LoggerInterface $logger,
        private readonly ParameterBagInterface $params,
    ) {
    }

    public function __invoke(Request $request): Response
    {
        $clientId = $request->query->get('client_id', '');
        $clientSecret = $request->query->get('client_secret', '');
        $connectionId = $request->query->get('connection_id', '');

        if (empty($clientId) || empty($clientSecret) || empty($connectionId)) {
            $this->logger->error('Blog Sync Callback: Missing required parameters');

            return new Response('Missing required parameters (client_id, client_secret, connection_id).', 400);
        }

        try {
            $siteUrl = $request->getHost();

            $this->connection->insert('tl_blog_sync_config', [
                'tstamp' => time(),
                'client_id' => $clientId,
                'client_secret' => $clientSecret,
                'connection_id' => $connectionId,
                'site_url' => $siteUrl,
                'api_url' => rtrim($this->params->get('blog_sync.frontend_url'), '/') . '/backend/contao/',
                'sync_enabled' => '1',
                'sync_interval' => 43200,
                'last_sync' => 0,
            ]);

            $this->logger->info('Blog Sync: New account created via connect callback', [
                'connection_id' => $connectionId,
                'site_url' => $siteUrl,
            ]);

            // Redirect to Contao backend module list
            return new RedirectResponse('/contao?do=blog_sync_config');

        } catch (\Exception $e) {
            $this->logger->error('Blog Sync Callback: Error creating account - ' . $e->getMessage());

            return new Response('Error creating account. Please try again.', 500);
        }
    }
}
