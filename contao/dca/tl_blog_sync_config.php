<?php

use Contao\DC_Table;

// Helper to get the frontend URL from container parameter or env var
function blogSyncGetFrontendUrl(): string
{
    $container = \Contao\System::getContainer();

    if ($container->hasParameter('blog_sync.frontend_url')) {
        return $container->getParameter('blog_sync.frontend_url');
    }

    return $_SERVER['BLOG_SYNC_FRONTEND_URL']
        ?? $_ENV['BLOG_SYNC_FRONTEND_URL']
        ?? getenv('BLOG_SYNC_FRONTEND_URL')
        ?: 'https://app.agency-powerstack.com';
}

$GLOBALS['TL_DCA']['tl_blog_sync_config'] = [
    'config' => [
        'dataContainer' => DC_Table::class,
        'enableVersioning' => false,
        'notCreatable' => true,
        'onload_callback' => [
            static function () {
                if (\Contao\Input::get('act') === null) {
                    $count = \Contao\Database::getInstance()
                        ->execute("SELECT COUNT(*) AS total FROM tl_blog_sync_config")
                        ->total;

                    if ((int) $count === 0) {
                        \Contao\Message::addInfo(
                            $GLOBALS['TL_LANG']['tl_blog_sync_config']['emptyList']
                                ?? 'Es sind noch keine Accounts vorhanden. Klicken Sie auf "Neuen Account anlegen", um einen Account hinzuzufügen.'
                        );
                    }

                    // Inject WebSocket config for JS
                    $config = \Contao\Database::getInstance()
                        ->execute("SELECT connection_id FROM tl_blog_sync_config WHERE sync_enabled = '1' LIMIT 1")
                        ->fetchAssoc();

                    if ($config && !empty($config['connection_id'])) {
                        $frontendUrl = blogSyncGetFrontendUrl();
                        $GLOBALS['TL_BODY'][] = sprintf(
                            '<script>window.BLOG_SYNC_CONFIG = %s;</script>',
                            json_encode([
                                'connectionId' => $config['connection_id'],
                                'wsUrl' => $frontendUrl,
                                'syncTriggerUrl' => \Contao\Environment::get('base') . 'contao/blog-sync/trigger-sync',
                                'requestToken' => \Contao\System::getContainer()->get('contao.csrf.token_manager')->getDefaultTokenValue(),
                            ])
                        );
                    }
                }
            },
        ],
        'ondelete_callback' => [
            static function (\Contao\DataContainer $dc): void {
                $listener = \Contao\System::getContainer()->get('AgencyPowerstack\ContaoBlogSyncBundle\EventListener\AccountDeleteListener');
                $listener->onDelete($dc);
            },
        ],
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
            'fields' => ['client_id', 'site_url'],
            'format' => 'Account (%s - %s)',
            'label_callback' => static function (array $row, string $label): string {
                $siteUrl = $row['site_url'] ?? '';
                $status = !empty($row['sync_enabled']) ? '&#9679;' : '&#9675;';
                $statusColor = !empty($row['sync_enabled']) ? 'green' : 'gray';
                return sprintf(
                    '<span style="font-weight:600"><span style="color:%s">%s</span> %s</span>',
                    $statusColor,
                    $status,
                    $siteUrl ?: $label
                );
            },
        ],
        'global_operations' => [
            'connect_new' => [
                'label' => &$GLOBALS['TL_LANG']['tl_blog_sync_config']['connect_new'],
                'href' => '',
                'class' => 'header_new',
                'button_callback' => static function (): string {
                    $frontendUrl = blogSyncGetFrontendUrl();
                    $connectUrl = rtrim($frontendUrl, '/') . '/connect/contao';

                    $callbackUrl = \Contao\Environment::get('base') . 'contao/blog-sync/callback';
                    $siteUrl = \Contao\Environment::get('host');

                    $url = $connectUrl . '?' . http_build_query([
                        'callback_url' => $callbackUrl,
                        'site_url' => $siteUrl,
                    ]);

                    return sprintf(
                        '<a href="%s" class="header_new" title="%s">%s</a>',
                        htmlspecialchars($url),
                        htmlspecialchars($GLOBALS['TL_LANG']['tl_blog_sync_config']['connect_new'][1] ?? 'Neuen Account anlegen'),
                        htmlspecialchars($GLOBALS['TL_LANG']['tl_blog_sync_config']['connect_new'][0] ?? 'Neuen Account anlegen')
                    );
                },
            ],
            'all' => [
                'href' => 'act=select',
                'class' => 'header_edit_all',
                'attributes' => 'onclick="Backend.getScrollOffset()" accesskey="e"',
            ],
        ],
        'operations' => [
            'edit' => [
                'href' => 'act=edit',
                'icon' => 'edit.svg',
            ],
            'delete' => [
                'href' => 'act=delete',
                'icon' => 'delete.svg',
                'attributes' => 'onclick="if(!confirm(\'Soll dieser Account wirklich gelöscht werden? Die Verbindung wird auch im Agency Powerstack Backend entfernt.\'))return false;Backend.getScrollOffset()"',
            ],
        ],
    ],
    'palettes' => [
        'default' => '{api_legend},client_id,client_secret,connection_id,api_url,site_url;{sync_legend},news_archive_id,sync_enabled;{status_legend},last_sync',
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
            'eval' => ['readonly' => true, 'maxlength' => 255, 'tl_class' => 'w50'],
            'sql' => "varchar(255) NOT NULL default ''",
        ],
        'client_secret' => [
            'label' => &$GLOBALS['TL_LANG']['tl_blog_sync_config']['client_secret'],
            'exclude' => true,
            'inputType' => 'text',
            'eval' => ['readonly' => true, 'maxlength' => 255, 'tl_class' => 'w50'],
            'sql' => "varchar(255) NOT NULL default ''",
        ],
        'connection_id' => [
            'label' => &$GLOBALS['TL_LANG']['tl_blog_sync_config']['connection_id'],
            'exclude' => true,
            'inputType' => 'text',
            'eval' => ['readonly' => true, 'maxlength' => 255, 'tl_class' => 'w50'],
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
            'eval' => ['readonly' => true, 'maxlength' => 500, 'tl_class' => 'w50', 'rgxp' => 'url'],
            'sql' => "varchar(500) NOT NULL default ''",
        ],
        'site_url' => [
            'label' => &$GLOBALS['TL_LANG']['tl_blog_sync_config']['site_url'],
            'exclude' => true,
            'inputType' => 'text',
            'eval' => ['readonly' => true, 'maxlength' => 500, 'tl_class' => 'w50'],
            'sql' => "varchar(500) NOT NULL default ''",
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
            'sql' => "int(10) unsigned NOT NULL default 43200",
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
