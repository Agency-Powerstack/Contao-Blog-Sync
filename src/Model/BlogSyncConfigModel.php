<?php

declare(strict_types=1);

namespace AgencyPowerstack\ContaoBlogSyncBundle\Model;

use Contao\Model;

/**
 * @property int    $id
 * @property int    $tstamp
 * @property string $connection_id
 * @property string $account_email
 * @property string $api_key
 * @property string $site_url
 * @property int    $news_archive_id
 * @property string $sync_enabled
 * @property int    $last_sync
 */
class BlogSyncConfigModel extends Model
{
    protected static $strTable = 'tl_blog_sync_config';
}
