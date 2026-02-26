<?php

declare(strict_types=1);

namespace AgencyPowerstack\ContaoBlogSyncBundle\Service;

use Doctrine\DBAL\Connection;

class SyncLogger
{
    public function __construct(
        private readonly Connection $connection,
    ) {
    }

    /**
     * Schreibt einen Sync-Log-Eintrag in die Datenbank.
     *
     * @param string $syncType  'webhook' | 'cron' | 'manual'
     * @param string $status    'success' | 'error'
     */
    public function log(
        int $configId,
        string $syncType,
        string $status,
        int $importedCount,
        int $failedCount,
        string $message,
        array $details = [],
    ): void {
        try {
            $this->connection->insert('tl_blog_sync_log', [
                'tstamp'         => time(),
                'pid'            => $configId,
                'sync_type'      => $syncType,
                'status'         => $status,
                'imported_count' => $importedCount,
                'failed_count'   => $failedCount,
                'message'        => $message,
                'details'        => !empty($details) ? json_encode($details) : null,
            ]);
        } catch (\Exception) {
            // Logging darf den Sync-Prozess nicht unterbrechen
        }
    }
}
