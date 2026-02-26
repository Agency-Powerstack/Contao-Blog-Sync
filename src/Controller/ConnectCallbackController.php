<?php

declare(strict_types=1);

namespace AgencyPowerstack\ContaoBlogSyncBundle\Controller;

use Doctrine\DBAL\Connection;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/contao/blog-sync/callback', name: 'blog_sync_callback')]
class ConnectCallbackController extends AbstractController
{
    public function __construct(
        private readonly Connection $connection,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function __invoke(Request $request): Response
    {
        $connectionId = $request->query->get('connection_id', '');
        $confirmUrl   = $request->query->get('confirm_url', '');

        if (empty($connectionId) || empty($confirmUrl)) {
            $this->logger->error('Blog Sync Callback: Missing required parameters (connection_id, confirm_url)');
            return new Response('Missing required parameters (connection_id, confirm_url).', 400);
        }

        // Security: confirm_url must be HTTPS (localhost allowed for dev)
        $isHttps = str_starts_with($confirmUrl, 'https://');
        $isLocalhost = (bool) preg_match('/^http:\/\/localhost(:\d+)?\//', $confirmUrl);
        if (!$isHttps && !$isLocalhost) {
            $this->logger->error('Blog Sync Callback: confirm_url must use HTTPS');
            return new Response('Invalid confirm_url: must use HTTPS (or localhost for dev).', 400);
        }

        try {
            // Generate a secure 64-character API key
            $apiKey  = bin2hex(random_bytes(32));
            $siteUrl = $request->getSchemeAndHttpHost();

            // Check if connection already exists (reconnect scenario)
            $existing = $this->connection->fetchAssociative(
                "SELECT id FROM tl_blog_sync_config WHERE connection_id = ?",
                [$connectionId]
            );

            if ($existing) {
                $this->connection->update('tl_blog_sync_config', [
                    'tstamp'  => time(),
                    'api_key' => $apiKey,
                ], ['connection_id' => $connectionId]);
            } else {
                $this->connection->insert('tl_blog_sync_config', [
                    'tstamp'           => time(),
                    'connection_id'    => $connectionId,
                    'api_key'          => $apiKey,
                    'site_url'         => $siteUrl,
                    'sync_enabled'     => '1',
                    'last_sync'        => 0,
                    'news_archive_id'  => 0,
                ]);
            }

            $this->logger->info('Blog Sync: Account created/updated via connect callback', [
                'connection_id' => $connectionId,
                'site_url'      => $siteUrl,
            ]);

            // Build Contao API URL (base URL of this Contao instance)
            $contaoApiUrl = $siteUrl;

            // Redirect to Agency Powerstack confirm endpoint
            $redirectUrl = $confirmUrl . '?' . http_build_query([
                'connection_id'   => $connectionId,
                'api_key'         => $apiKey,
                'contao_api_url'  => $contaoApiUrl,
            ]);

            return new RedirectResponse($redirectUrl);

        } catch (\Exception $e) {
            $this->logger->error('Blog Sync Callback: Error - ' . $e->getMessage());
            return new Response('Error processing callback. Please try again.', 500);
        }
    }
}
