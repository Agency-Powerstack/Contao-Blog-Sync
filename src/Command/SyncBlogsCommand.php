<?php

declare(strict_types=1);

namespace AgencyPowerstack\ContaoBlogSyncBundle\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use AgencyPowerstack\ContaoBlogSyncBundle\Service\ApiClient;
use AgencyPowerstack\ContaoBlogSyncBundle\Service\BlogImporter;
use Contao\CoreBundle\Framework\ContaoFramework;
use Doctrine\DBAL\Connection;

#[AsCommand(
    name: 'blogsync:sync',
    description: 'Synchronisiert Blogs von Agency Powerstack'
)]
class SyncBlogsCommand extends Command
{
    private ApiClient $apiClient;
    private BlogImporter $blogImporter;
    private Connection $connection;
    private ContaoFramework $framework;

    public function __construct(
        ApiClient $apiClient,
        BlogImporter $blogImporter,
        Connection $connection,
        ContaoFramework $framework
    ) {
        parent::__construct();
        
        $this->apiClient = $apiClient;
        $this->blogImporter = $blogImporter;
        $this->connection = $connection;
        $this->framework = $framework;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        
        $this->framework->initialize();

        $io->title('Blog Synchronisation');

        // Konfiguration laden
        $config = $this->loadConfig();
        
        if (!$config) {
            $io->error('Keine Konfiguration gefunden. Bitte konfiguriere das Plugin im Backend.');
            return Command::FAILURE;
        }

        // Authentifizierung
        $io->section('Authentifizierung');
        
        if (!empty($config['access_token'])) {
            $this->apiClient->setAccessToken($config['access_token']);
            $io->success('Access Token geladen');
        } else {
            $io->text('Authentifiziere mit Client Credentials...');
            
            if (!$this->apiClient->authenticate($config['client_id'], $config['client_secret'])) {
                $io->error('Authentifizierung fehlgeschlagen');
                return Command::FAILURE;
            }
            
            $io->success('Erfolgreich authentifiziert');
        }

        // Blogs abrufen
        $io->section('Blogs abrufen');
        
        $lastSync = $config['last_sync'] ?? null;
        $blogs = $this->apiClient->fetchNewBlogs($lastSync);
        
        if (empty($blogs)) {
            $io->info('Keine neuen Blogs gefunden');
            return Command::SUCCESS;
        }

        $io->text(sprintf('Gefunden: %d neue Blog(s)', count($blogs)));

        // Blogs importieren
        $io->section('Blogs importieren');
        
        $archiveId = (int) $config['news_archive_id'];
        $results = $this->blogImporter->importMultiple($blogs, $archiveId);

        // Ergebnisse
        $io->success(sprintf(
            'Import abgeschlossen: %d erfolgreich, %d fehlgeschlagen',
            $results['success'],
            $results['failed']
        ));

        // Bestätigungen senden
        foreach ($results['imported_ids'] as $blogId) {
            if ($blogId) {
                $this->apiClient->confirmBlogImport($blogId);
            }
        }

        // Letzten Sync-Zeitpunkt speichern
        $this->updateLastSync();

        return Command::SUCCESS;
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
            // Log error but don't fail
        }
    }
}