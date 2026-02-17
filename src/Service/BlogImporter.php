<?php

declare(strict_types=1);

namespace AgencyPowerstack\ContaoBlogSyncBundle\Service;

use Contao\NewsModel;
use Contao\NewsArchiveModel;
use Contao\FilesModel;
use Contao\Dbafs;
use Psr\Log\LoggerInterface;

class BlogImporter
{
    private LoggerInterface $logger;

    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    /**
     * Importiert einen Blog-Post als Contao News-Eintrag
     */
    public function importBlog(array $blogData, int $archiveId): ?NewsModel
    {
        try {
            // Prüfe ob Archive existiert
            $archive = NewsArchiveModel::findByPk($archiveId);
            if (!$archive) {
                $this->logger->error("News archive with ID {$archiveId} not found");
                return null;
            }

            // Prüfe ob Blog bereits importiert wurde (anhand externer ID)
            $existingNews = NewsModel::findBy(
                ['externalId=?'],
                [$blogData['id'] ?? '']
            );

            if ($existingNews) {
                $this->logger->info("Blog {$blogData['id']} already imported, updating...");
                $news = $existingNews;
            } else {
                $news = new NewsModel();
                $news->tstamp = time();
            }

            // Basis-Daten
            $news->pid = $archiveId;
            $news->headline = $blogData['title'] ?? 'Untitled';
            $news->alias = $this->generateAlias($blogData['title'] ?? 'untitled');
            $news->author = $blogData['author_id'] ?? 1;
            $news->date = $blogData['published_at'] ?? time();
            $news->time = $blogData['published_at'] ?? time();
            
            // Inhalt
            $news->teaser = $blogData['excerpt'] ?? '';
            $news->text = $this->convertContent($blogData['content'] ?? '');
            
            // Veröffentlichung
            $news->published = $blogData['status'] === 'published' ? '1' : '';
            
            // Externe ID speichern
            $news->externalId = $blogData['id'] ?? '';
            
            // Featured Image
            if (!empty($blogData['featured_image'])) {
                $news->addImage = '1';
                $news->singleSRC = $this->downloadImage($blogData['featured_image']);
            }

            // SEO
            if (!empty($blogData['meta_title'])) {
                $news->pageTitle = $blogData['meta_title'];
            }
            if (!empty($blogData['meta_description'])) {
                $news->description = $blogData['meta_description'];
            }

            $news->save();
            
            $this->logger->info("Successfully imported blog: {$news->headline} (ID: {$news->id})");
            
            return $news;

        } catch (\Exception $e) {
            $this->logger->error("Error importing blog: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Generiert einen URL-Alias
     */
    private function generateAlias(string $title): string
    {
        $alias = standardize(\StringUtil::restoreBasicEntities($title));
        
        // Eindeutigkeit prüfen
        $count = 0;
        $testAlias = $alias;
        
        while (NewsModel::findByAlias($testAlias)) {
            $count++;
            $testAlias = $alias . '-' . $count;
        }
        
        return $testAlias;
    }

    /**
     * Konvertiert Content (z.B. Markdown zu HTML)
     */
    private function convertContent(string $content): string
    {
        // Hier kannst du Markdown-Konvertierung oder andere Transformationen durchführen
        // Für HTML-Content einfach zurückgeben
        return $content;
    }

    /**
     * Lädt ein Bild herunter und fügt es dem DBAFS hinzu
     */
    private function downloadImage(string $imageUrl): ?string
    {
        try {
            $targetDir = 'files/blog-images';
            $targetPath = $targetDir . '/' . basename($imageUrl);
            
            // Verzeichnis erstellen falls nicht vorhanden
            if (!is_dir(TL_ROOT . '/' . $targetDir)) {
                mkdir(TL_ROOT . '/' . $targetDir, 0755, true);
            }

            // Bild herunterladen
            $imageContent = file_get_contents($imageUrl);
            if ($imageContent === false) {
                $this->logger->warning("Could not download image: {$imageUrl}");
                return null;
            }

            file_put_contents(TL_ROOT . '/' . $targetPath, $imageContent);
            
            // Zu DBAFS hinzufügen
            Dbafs::addResource($targetPath);
            
            $file = FilesModel::findByPath($targetPath);
            if ($file) {
                return $file->uuid;
            }

            return null;

        } catch (\Exception $e) {
            $this->logger->error("Error downloading image: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Importiert mehrere Blogs
     */
    public function importMultiple(array $blogs, int $archiveId): array
    {
        $results = [
            'success' => 0,
            'failed' => 0,
            'imported_ids' => []
        ];

        foreach ($blogs as $blog) {
            $news = $this->importBlog($blog, $archiveId);
            
            if ($news) {
                $results['success']++;
                $results['imported_ids'][] = $blog['id'] ?? null;
            } else {
                $results['failed']++;
            }
        }

        return $results;
    }
}