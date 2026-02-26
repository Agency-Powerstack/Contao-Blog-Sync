<?php

declare(strict_types=1);

namespace AgencyPowerstack\ContaoBlogSyncBundle\Service;

use Contao\Dbafs;
use Contao\FilesModel;
use Contao\NewsArchiveModel;
use Contao\NewsModel;
use Doctrine\DBAL\Connection;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpKernel\KernelInterface;

class BlogImporter
{
    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly KernelInterface $kernel,
        private readonly Connection $connection,
    ) {
    }

    /**
     * Importiert einen Blog-Post als Contao News-Eintrag
     */
    public function importBlog(array $blogData, int $archiveId): ?NewsModel
    {
        try {
            $archive = NewsArchiveModel::findByPk($archiveId);
            if (!$archive) {
                $this->logger->error("News archive with ID {$archiveId} not found");
                return null;
            }

            $externalId = $blogData['id'] ?? '';
            if (empty($externalId)) {
                $this->logger->error('Blog has no ID, skipping');
                return null;
            }

            // Prüfe ob Blog bereits importiert wurde (anhand externer ID)
            $existingNews = NewsModel::findOneBy(['externalId=?'], [$externalId]);
            $isUpdate = false;

            if ($existingNews !== null) {
                $this->logger->info("Blog {$externalId} already imported, updating...");
                $news = $existingNews;
                $isUpdate = true;
            } else {
                $news = new NewsModel();
                $news->tstamp = time();
            }

            // Basis-Daten
            $news->pid = $archiveId;
            $news->headline = $blogData['title'] ?? 'Untitled';
            $news->author = 0;

            if (!$isUpdate) {
                $news->alias = $this->generateAlias($blogData['title'] ?? 'untitled');
            }

            // Zeitstempel
            $createdAt = !empty($blogData['createdAt'])
                ? strtotime($blogData['createdAt'])
                : time();
            $news->date = $createdAt;
            $news->time = $createdAt;

            // Vollständiger HTML-Inhalt – bevorzuge targetSystemCode (Contao-spezifisch)
            $rawHtml = $blogData['targetSystemCode'] ?? $blogData['htmlContent'] ?? '';

            // <img>-Tags und Wrapper um {{contao_image::N}}-Platzhalter entfernen,
            // damit preg_split() saubere HTML-Segmente erzeugt (keine kaputten <img>-Reste)
            $rawHtml = $this->normalizeImagePlaceholders($rawHtml);

            // Bilder registrieren, UUID-Map aufbauen (index → binary UUID)
            $imageUuids = $this->collectContentImageUuids(
                $blogData['contentImageUrls'] ?? [],
                $externalId
            );

            // Teaser = gekürzter Plaintext (max. 300 Zeichen) aus textContent
            $news->teaser = $this->buildTeaser($blogData['textContent'] ?? '', $rawHtml);

            // Veröffentlichungsstatus
            $status = $blogData['status'] ?? '';
            $news->published = ($status === 'PUBLISH') ? 1 : 0;

            // Externe ID speichern
            $news->externalId = $externalId;

            // Featured Image
            if (!empty($blogData['postImageUrl'])) {
                $imageUuid = $this->ensureImageRegistered(
                    $blogData['postImageUrl'],
                    $externalId,
                    'featured'
                );
                if ($imageUuid) {
                    $news->addImage = '1';
                    $news->singleSRC = $imageUuid;
                }
            }

            // SEO
            if (!empty($blogData['metaDescription'])) {
                $news->description = $blogData['metaDescription'];
            }
            if (!empty($blogData['title'])) {
                $news->pageTitle = $blogData['title'];
            }

            $news->save();

            // Vollständiger Artikelinhalt als tl_content-Elemente speichern
            $this->updateContentElements((int) $news->id, $rawHtml, $imageUuids);

            $this->logger->info("Successfully imported blog: {$news->headline} (ID: {$news->id})");

            return $news;

        } catch (\Throwable $e) {
            $this->logger->error("Error importing blog: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Baut den Teaser-Text (max. 2000 Zeichen) aus dem Klartext-Inhalt.
     * Bevorzugt textContent (bereits Plaintext), Fallback: HTML-Tags entfernen.
     */
    private function buildTeaser(string $textContent, string $htmlFallback): string
    {
        $text = trim($textContent);

        if ($text === '') {
            $text = trim(strip_tags($htmlFallback));
        }

        if (mb_strlen($text) > 300) {
            $text = mb_substr($text, 0, 297) . '...';
        }

        return $text;
    }

    /**
     * Löscht bestehende auto-generierte tl_content-Einträge und legt abwechselnd
     * HTML- und native Image-Elemente an (aufgesplittet an {{contao_image::N}}-Platzhaltern).
     *
     * @param array<int, string> $imageUuids index → binary UUID
     */
    private function updateContentElements(int $newsId, string $rawHtml, array $imageUuids): void
    {
        try {
            $this->connection->delete('tl_content', [
                'pid'    => $newsId,
                'ptable' => 'tl_news',
            ]);

            if (trim($rawHtml) === '') {
                return;
            }

            // An {{contao_image::N}}-Platzhaltern aufsplitten (Delimiter werden miterfasst)
            $parts = preg_split('/\{\{contao_image::(\d+)\}\}/', $rawHtml, -1, PREG_SPLIT_DELIM_CAPTURE);

            $sorting = 128;
            $i       = 0;
            $count   = count($parts);

            while ($i < $count) {
                // Gerades Token: HTML-Segment
                $htmlSegment = $parts[$i];
                $safeHtml    = str_replace(['{{', '}}'], ['&#123;&#123;', '&#125;&#125;'], $htmlSegment);

                if (trim($safeHtml) !== '') {
                    $this->connection->insert('tl_content', [
                        'pid'       => $newsId,
                        'ptable'    => 'tl_news',
                        'tstamp'    => time(),
                        'type'      => 'html',
                        'html'      => $safeHtml,
                        'invisible' => 0,
                        'sorting'   => $sorting,
                    ]);
                    $sorting += 128;
                }
                $i++;

                // Ungerades Token: Bild-Index (aus Delimiter-Capture)
                if ($i < $count) {
                    $index = (int) $parts[$i];
                    if (isset($imageUuids[$index])) {
                        $this->connection->insert('tl_content', [
                            'pid'       => $newsId,
                            'ptable'    => 'tl_news',
                            'tstamp'    => time(),
                            'type'      => 'image',
                            'singleSRC' => $imageUuids[$index],
                            // Contao's ResizeCalculator betrachtet width=0,height=0 als "leer" und
                            // gibt den Rohpfad zurück. Erst eine Dimension > 0 triggert den
                            // Image-Processor → /assets/images/... URL.
                            // 1200px proportional: downscale für große Bilder, upscale für kleine
                            // (alle erhalten einen /assets/images/... Link).
                            'size'      => serialize([1200, 0, 'proportional']),
                            'floating'  => 'above',
                            'invisible' => 0,
                            'sorting'   => $sorting,
                        ]);
                        $sorting += 128;
                    }
                    $i++;
                }
            }

            $this->logger->info("tl_content elements created for news {$newsId} (sorting up to " . ($sorting - 128) . ')');
        } catch (\Throwable $e) {
            $this->logger->error("tl_content insert failed for news {$newsId}: " . $e->getMessage());
        }
    }

    /**
     * Normalisiert {{contao_image::N}}-Platzhalter im HTML.
     *
     * Die KI erzeugt: <figure><img src="{{contao_image::N}}" alt="..."><figcaption>...</figcaption></figure>
     * Nach Normalisierung: {{contao_image::N}} als bloßer Platzhalter ohne Wrapper,
     * damit preg_split() saubere HTML-Segmente erzeugt.
     *
     * Schritte:
     *  1. <img src="{{...}}"> → {{...}}  (Platzhalter aus src-Attribut extrahieren)
     *  2. <figure>...(nur Platzhalter + opt. figcaption)...</figure> → {{...}}
     *  3. <p>...(nur Platzhalter)...</p> → {{...}}
     */
    private function normalizeImagePlaceholders(string $html): string
    {
        // 1. <img>-Tag entfernen, Platzhalter aus src-Attribut extrahieren
        $html = preg_replace(
            '/<img\b[^>]*\bsrc="(\{\{contao_image::\d+\}\})"[^>]*\/?>/i',
            '$1',
            $html
        );

        // 2. <figure>-Wrapper abstreifen (mit optionaler figcaption)
        $html = preg_replace(
            '/<figure\b[^>]*>\s*(\{\{contao_image::\d+\}\})\s*(?:<figcaption\b[^>]*>.*?<\/figcaption>\s*)?<\/figure>/si',
            '$1',
            $html
        );

        // 3. <p>-Wrapper abstreifen
        $html = preg_replace(
            '/<p\b[^>]*>\s*(\{\{contao_image::\d+\}\})\s*<\/p>/si',
            '$1',
            $html
        );

        return $html;
    }

    /**
     * Registriert alle Content-Bilder in Contao und gibt eine Map index → binary UUID zurück.
     *
     * @param array<int|string, string> $imageUrls
     * @return array<int, string>
     */
    private function collectContentImageUuids(array $imageUrls, string $postId): array
    {
        $uuids = [];
        foreach ($imageUrls as $index => $imageUrl) {
            if (empty($imageUrl)) {
                continue;
            }
            $uuid = $this->ensureImageRegistered($imageUrl, $postId, 'content-' . $index);
            if ($uuid !== null) {
                $uuids[(int) $index] = $uuid;
            }
        }
        return $uuids;
    }

    /**
     * Stellt sicher, dass ein Bild in Contao vorhanden ist.
     *
     * Prüft zuerst ob das Bild bereits im DBAFS registriert ist (anhand Zielpfad).
     * Falls ja, wird die UUID des bestehenden Eintrags zurückgegeben (kein Download).
     * Falls nein, wird das Bild heruntergeladen und im DBAFS registriert.
     *
     * @return string|null UUID des Bildes, oder null bei Fehler
     */
    private function ensureImageRegistered(string $imageUrl, string $postId, string $suffix): ?string
    {
        try {
            $projectDir = $this->kernel->getProjectDir();
            $targetDir  = 'files/blog-images/' . $postId;
            $extension  = $this->guessExtension($imageUrl);
            $filename   = $this->guessFilename($imageUrl, $suffix) . '.' . $extension;
            $targetPath = $targetDir . '/' . $filename;

            // Bereits vorhanden? → UUID zurückgeben ohne Download
            $existingFile = FilesModel::findByPath($targetPath);
            if ($existingFile !== null) {
                $this->logger->info("Image already registered in Contao: {$targetPath}");
                return $existingFile->uuid;
            }

            // Verzeichnis anlegen falls nötig
            $fullTargetDir = $projectDir . '/' . $targetDir;
            if (!is_dir($fullTargetDir)) {
                mkdir($fullTargetDir, 0755, true);
            }

            // Bild herunterladen und validieren
            $imageContent = $this->downloadFile($imageUrl);
            if ($imageContent === null) {
                $this->logger->warning("Could not download image from: " . substr($imageUrl, 0, 120));
                return null;
            }

            // Bild speichern und im DBAFS registrieren
            file_put_contents($projectDir . '/' . $targetPath, $imageContent);
            Dbafs::addResource($targetPath);

            $file = FilesModel::findByPath($targetPath);
            if ($file) {
                $this->logger->info("Image registered in Contao: {$targetPath}");
                return $file->uuid;
            }

            return null;

        } catch (\Throwable $e) {
            $this->logger->error("Error registering image: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Lädt eine Datei von einer URL herunter und validiert den Inhalt als Bild.
     *
     * Prüft den HTTP-Status (z.B. abgelaufene S3 Presigned-URLs geben 403 zurück)
     * und stellt sicher, dass der Inhalt tatsächlich ein Bild ist (Magic-Byte-Check).
     */
    private function downloadFile(string $url): ?string
    {
        $this->logger->debug('Downloading image from: ' . substr($url, 0, 100));

        $context = stream_context_create([
            'http' => [
                'timeout'         => 30,
                'user_agent'      => 'ContaoBlogSync/1.0',
                'ignore_errors'   => true,
                'follow_location' => 1,
            ],
            'ssl' => [
                'verify_peer'      => true,
                'verify_peer_name' => true,
            ],
        ]);

        $content = @file_get_contents($url, false, $context);

        $httpStatus = $http_response_header[0] ?? 'n/a';
        $this->logger->debug(sprintf('Image response: %s, %d bytes', $httpStatus, is_string($content) ? strlen($content) : -1));

        if ($content === false || $content === '') {
            $this->logger->warning('Image download returned empty/false – likely network error (hostname not resolvable?)');
            return null;
        }

        // HTTP-Statuscode prüfen – $http_response_header wird von PHP nach file_get_contents gesetzt
        if (!empty($http_response_header) && is_array($http_response_header)) {
            if (!preg_match('#HTTP/[\d.]+\s+2\d\d#', $httpStatus)) {
                $this->logger->warning("Image download failed – HTTP: {$httpStatus}");
                return null;
            }
        }

        // Validieren: Inhalt muss ein gültiges Bild sein (schützt vor XML/HTML-Fehlerantworten)
        if (!$this->isValidImageContent($content)) {
            $this->logger->warning(
                sprintf('Downloaded content is not a valid image (%d bytes, starts: %s)', strlen($content), substr($content, 0, 32))
            );
            return null;
        }

        return $content;
    }

    /**
     * Prüft anhand der Magic Bytes ob der Inhalt ein bekanntes Bildformat ist.
     * Robuster als getimagesizefromstring() – unterstützt auch AVIF, WebP, SVG.
     */
    private function isValidImageContent(string $content): bool
    {
        if (strlen($content) < 8) {
            return false;
        }

        return str_starts_with($content, "\xFF\xD8\xFF")         // JPEG
            || str_starts_with($content, "\x89PNG\r\n\x1a\n")    // PNG
            || str_starts_with($content, "GIF8")                  // GIF
            || str_starts_with($content, "RIFF")                  // WebP (RIFF....WEBP)
            || substr($content, 4, 4) === 'ftyp'                  // AVIF / HEIC (ISO BMFF)
            || str_contains(substr($content, 0, 512), '<svg');    // SVG (inline oder mit BOM)
    }

    /**
     * Leitet einen sinnvollen Dateinamen aus dem URL-Pfad ab.
     * Fällt auf $fallback zurück wenn kein sprechender Name erkennbar ist.
     */
    private function guessFilename(string $url, string $fallback): string
    {
        $path = parse_url($url, PHP_URL_PATH);
        if ($path) {
            $name = pathinfo($path, PATHINFO_FILENAME);
            // Reine UUIDs/Hashes (32+ hex-Zeichen) werden nicht als Dateiname verwendet
            if ($name !== '' && strlen($name) < 100 && !preg_match('/^[a-f0-9\-]{32,}$/i', $name)) {
                return preg_replace('/[^a-zA-Z0-9_-]/', '-', $name);
            }
        }

        return $fallback;
    }

    /**
     * Versucht die Dateiendung aus der URL abzuleiten
     */
    private function guessExtension(string $url): string
    {
        $path = parse_url($url, PHP_URL_PATH);
        if ($path) {
            $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
            if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg', 'avif'])) {
                return $ext;
            }
        }
        return 'jpg';
    }

    /**
     * Generiert einen URL-Alias
     */
    private function generateAlias(string $title): string
    {
        $alias = \Contao\StringUtil::standardize(\Contao\StringUtil::restoreBasicEntities($title));

        $count = 0;
        $testAlias = $alias;

        while (NewsModel::findByAlias($testAlias)) {
            $count++;
            $testAlias = $alias . '-' . $count;
        }

        return $testAlias;
    }

    /**
     * Importiert mehrere Blogs
     */
    public function importMultiple(array $blogs, int $archiveId): array
    {
        $results = [
            'success'      => 0,
            'failed'       => 0,
            'imported_ids' => [],
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
