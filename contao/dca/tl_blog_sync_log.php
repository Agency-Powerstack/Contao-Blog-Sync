<?php

use Contao\DC_Table;

$GLOBALS['TL_DCA']['tl_blog_sync_log'] = [
    'config' => [
        'dataContainer'   => DC_Table::class,
        'ptable'          => 'tl_blog_sync_config',
        'enableVersioning' => false,
        'notCreatable'    => true,
        'notEditable'     => true,
        'sql' => [
            'keys' => [
                'id'  => 'primary',
                'pid' => 'index',
            ],
        ],
    ],
    'list' => [
        'sorting' => [
            'mode'        => 4,
            'fields'      => ['tstamp DESC'],
            'panelLayout' => 'filter;search,limit',
            'headerFields' => ['account_email', 'site_url'],
        ],
        'label' => [
            'fields'         => ['tstamp', 'sync_type', 'status', 'imported_count', 'failed_count', 'message'],
            'label_callback' => static function (array $row): string {
                $date = date('d.m.Y H:i:s', (int) $row['tstamp']);
                $statusColor = ($row['status'] === 'success') ? '#4caf50' : '#f44336';
                $statusLabel = htmlspecialchars((string) $row['status']);
                $typeLabel   = htmlspecialchars((string) $row['sync_type']);
                $message     = htmlspecialchars((string) ($row['message'] ?? ''));

                return sprintf(
                    '<span style="color:#666;font-size:0.9em">[%s]</span> '
                        . '<span style="color:%s;font-weight:bold">%s</span> '
                        . '<span style="color:#888">(%s)</span> — '
                        . 'importiert: <strong>%d</strong>, fehlgeschlagen: <strong>%d</strong>'
                        . '%s',
                    $date,
                    $statusColor,
                    $statusLabel,
                    $typeLabel,
                    (int) $row['imported_count'],
                    (int) $row['failed_count'],
                    $message ? ' — ' . $message : ''
                );
            },
        ],
        'global_operations' => [],
        'operations' => [
            'show' => [
                'href'  => 'act=show',
                'icon'  => 'show.svg',
                'label' => &$GLOBALS['TL_LANG']['tl_blog_sync_log']['show'],
            ],
        ],
    ],
    'palettes' => [
        'default' => 'tstamp,pid,sync_type,status,imported_count,failed_count,message,details',
    ],
    'fields' => [
        'id' => [
            'sql' => "int(10) unsigned NOT NULL auto_increment",
        ],
        'pid' => [
            'label' => &$GLOBALS['TL_LANG']['tl_blog_sync_log']['pid'],
            'sql'   => "int(10) unsigned NOT NULL default 0",
        ],
        'tstamp' => [
            'label' => &$GLOBALS['TL_LANG']['tl_blog_sync_log']['tstamp'],
            'sql'   => "int(10) unsigned NOT NULL default 0",
        ],
        'sync_type' => [
            'label'  => &$GLOBALS['TL_LANG']['tl_blog_sync_log']['sync_type'],
            'filter' => true,
            'sql'    => "varchar(32) NOT NULL default ''",
        ],
        'status' => [
            'label'  => &$GLOBALS['TL_LANG']['tl_blog_sync_log']['status'],
            'filter' => true,
            'sql'    => "varchar(16) NOT NULL default ''",
        ],
        'imported_count' => [
            'label' => &$GLOBALS['TL_LANG']['tl_blog_sync_log']['imported_count'],
            'sql'   => "int(10) unsigned NOT NULL default 0",
        ],
        'failed_count' => [
            'label' => &$GLOBALS['TL_LANG']['tl_blog_sync_log']['failed_count'],
            'sql'   => "int(10) unsigned NOT NULL default 0",
        ],
        'message' => [
            'label'  => &$GLOBALS['TL_LANG']['tl_blog_sync_log']['message'],
            'search' => true,
            'sql'    => "text NULL",
        ],
        'details' => [
            'label' => &$GLOBALS['TL_LANG']['tl_blog_sync_log']['details'],
            'sql'   => "longtext NULL",
        ],
    ],
];
