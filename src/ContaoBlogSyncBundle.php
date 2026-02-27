<?php

declare(strict_types=1);

namespace AgencyPowerstack\ContaoBlogSyncBundle;

use Symfony\Component\HttpKernel\Bundle\Bundle;

/**
 * Bundle entry point for the Agency Powerstack Blog Sync Contao extension.
 *
 * Overrides getPath() to return the bundle root (one level above /src) so that
 * Contao finds the contao/ resources directory for DCA, language files and config.
 */
final class ContaoBlogSyncBundle extends Bundle
{
    public function getPath(): string
    {
        return \dirname(__DIR__);
    }
}