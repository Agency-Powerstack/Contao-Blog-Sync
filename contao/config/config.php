<?php

/**
 * Backend modules
 */
$GLOBALS['BE_MOD']['agency_powerstack']['blog_sync_config'] = [
    'tables' => ['tl_blog_sync_config', 'tl_blog_sync_log'],
    'icon'   => 'bundles/contaoblogsync/icons/logo-ap.svg',
];

/**
 * Models
 */
$GLOBALS['TL_MODELS']['tl_blog_sync_config'] = 'AgencyPowerstack\ContaoBlogSyncBundle\Model\BlogSyncConfigModel';
