<?php

use Contao\DC_Table;

$GLOBALS['TL_DCA']['tl_blog_sync_config'] = [
    'config' => [
        'dataContainer' => DC_Table::class,
        'enableVersioning' => false,
        'sql' => [
            'keys' => [
                'id' => 'primary',
            ],
        ],
    ],
    'list' => [
        'sorting' => [
            'mode' => 1,
            'fields' => ['id'],
            'panelLayout' => '',
        ],
        'label' => [
            'fields' => ['client_id'],
            'format' => 'Blog Sync Konfiguration (Client: %s)',
        ],
        'operations' => [
            'edit' => [
                'href' => 'act=edit',
                'icon' => 'edit.svg',
            ],
            'delete' => [
                'href' => 'act=delete',
                'icon' => 'delete.svg',
            ],
        ],
    ],
    'palettes' => [
        'default' => '{api_legend},client_id,client_secret,access_token,api_url;{sync_legend},news_archive_id,sync_enabled,sync_interval;{status_legend},last_sync',
    ],
    'fields' => [
        'id' => [
            'sql' => "int(10) unsigned NOT NULL auto_increment",
        ],
        'tstamp' => [
            'sql' => "int(10) unsigned NOT NULL default 0",
        ],
        'client_id' => [
            'label' => &$GLOBALS['TL_LANG']['tl_blog_sync_config']['client_id'],
            'exclude' => true,
            'inputType' => 'text',
            'eval' => ['mandatory' => true, 'maxlength' => 255, 'tl_class' => 'w50'],
            'sql' => "varchar(255) NOT NULL default ''",
        ],
        'client_secret' => [
            'label' => &$GLOBALS['TL_LANG']['tl_blog_sync_config']['client_secret'],
            'exclude' => true,
            'inputType' => 'text',
            'eval' => ['mandatory' => true, 'maxlength' => 255, 'tl_class' => 'w50'],
            'sql' => "varchar(255) NOT NULL default ''",
        ],
        'access_token' => [
            'label' => &$GLOBALS['TL_LANG']['tl_blog_sync_config']['access_token'],
            'exclude' => true,
            'inputType' => 'text',
            'eval' => ['maxlength' => 500, 'tl_class' => 'w50'],
            'sql' => "varchar(500) NOT NULL default ''",
        ],
        'api_url' => [
            'label' => &$GLOBALS['TL_LANG']['tl_blog_sync_config']['api_url'],
            'exclude' => true,
            'inputType' => 'text',
            'eval' => ['maxlength' => 500, 'tl_class' => 'w50', 'rgxp' => 'url'],
            'sql' => "varchar(500) NOT NULL default 'https://app.agency-powerstack.com/backend/integrations/contao'",
        ],
        'news_archive_id' => [
            'label' => &$GLOBALS['TL_LANG']['tl_blog_sync_config']['news_archive_id'],
            'exclude' => true,
            'inputType' => 'select',
            'foreignKey' => 'tl_news_archive.title',
            'eval' => ['mandatory' => true, 'tl_class' => 'w50', 'includeBlankOption' => true],
            'sql' => "int(10) unsigned NOT NULL default 0",
        ],
        'sync_enabled' => [
            'label' => &$GLOBALS['TL_LANG']['tl_blog_sync_config']['sync_enabled'],
            'exclude' => true,
            'inputType' => 'checkbox',
            'eval' => ['tl_class' => 'w50'],
            'sql' => "char(1) NOT NULL default ''",
        ],
        'sync_interval' => [
            'label' => &$GLOBALS['TL_LANG']['tl_blog_sync_config']['sync_interval'],
            'exclude' => true,
            'inputType' => 'select',
            'options' => [
                '3600' => '1 Stunde',
                '7200' => '2 Stunden',
                '21600' => '6 Stunden',
                '43200' => '12 Stunden',
                '86400' => '24 Stunden',
            ],
            'eval' => ['tl_class' => 'w50'],
            'sql' => "int(10) unsigned NOT NULL default 3600",
        ],
        'last_sync' => [
            'label' => &$GLOBALS['TL_LANG']['tl_blog_sync_config']['last_sync'],
            'exclude' => true,
            'inputType' => 'text',
            'eval' => ['readonly' => true, 'rgxp' => 'datim', 'tl_class' => 'w50'],
            'sql' => "int(10) unsigned NOT NULL default 0",
        ],
    ],
];
