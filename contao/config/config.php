<?php

/**
 * Backend modules
 */
$GLOBALS['BE_MOD']['agency_powerstack']['blog_sync_config'] = [
    'tables' => ['tl_blog_sync_config'],
    'icon' => 'bundles/contaoblogsync/icons/blog-sync.svg',
    'javascript' => 'bundles/contaoblogsync/js/websocket.js',
];

/**
 * Cron Jobs
 */
$GLOBALS['TL_CRON']['hourly'][] = ['AgencyPowerstack\ContaoBlogSyncBundle\Cron\BlogSyncCron', 'run'];

/**
 * Models
 */
$GLOBALS['TL_MODELS']['tl_blog_sync_config'] = 'AgencyPowerstack\ContaoBlogSyncBundle\Model\BlogSyncConfigModel';

/**
 * Erweiterung der tl_news Tabelle für externe ID
 */
$GLOBALS['TL_DCA']['tl_news']['fields']['externalId'] = [
    'label' => ['Externe ID', 'ID aus dem Agency Powerstack System'],
    'exclude' => true,
    'inputType' => 'text',
    'eval' => ['maxlength' => 255, 'tl_class' => 'w50'],
    'sql' => "varchar(255) NOT NULL default ''"
];
