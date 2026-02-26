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
        'dataContainer'   => DC_Table::class,
        'ctable'          => ['tl_blog_sync_log'],
        'enableVersioning' => false,
        'notCreatable'    => true,
        'onload_callback' => [
            static function () {
                $act = \Contao\Input::get('act');

                // Listenansicht: Infomeldung wenn leer
                if ($act === null) {
                    $count = \Contao\Database::getInstance()
                        ->execute("SELECT COUNT(*) AS total FROM tl_blog_sync_config")
                        ->total;

                    if ((int) $count === 0) {
                        \Contao\Message::addInfo(
                            $GLOBALS['TL_LANG']['tl_blog_sync_config']['emptyList']
                                ?? 'Es sind noch keine Accounts vorhanden. Klicken Sie auf "Neuen Account anlegen", um einen Account hinzuzufügen.'
                        );
                    }
                }

                // Bearbeitungsansicht: API-Key und Push-URL anzeigen
                if ($act === 'edit') {
                    $id = (int) \Contao\Input::get('id');
                    if ($id > 0) {
                        $row = \Contao\Database::getInstance()
                            ->prepare("SELECT api_key FROM tl_blog_sync_config WHERE id=?")
                            ->execute($id)
                            ->fetchAssoc();

                        if ($row && !empty($row['api_key'])) {
                            $pushUrl = \Contao\Environment::get('base') . 'contao/blog-sync/api/push';
                            \Contao\Message::addInfo(
                                '<strong>Push-API URL für Agency Powerstack:</strong><br>'
                                . '<code style="word-break:break-all">' . htmlspecialchars($pushUrl) . '</code><br>'
                                . '<small>Diese URL und den API-Key werden automatisch bei der Verbindung übertragen.</small>'
                            );
                        }
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
            'mode'        => 1,
            'fields'      => ['id'],
            'panelLayout' => '',
        ],
        'label' => [
            'fields'         => ['connection_id', 'account_email', 'site_url'],
            'format'         => 'Account (%s)',
            'label_callback' => static function (array $row, string $label): string {
                $displayName = $row['account_email'] ?: ($row['site_url'] ?: $label);
                $status = !empty($row['sync_enabled']) ? '&#9679;' : '&#9675;';
                $statusColor = !empty($row['sync_enabled']) ? 'green' : 'gray';

                $warning = '';
                if (empty($row['news_archive_id']) || (int) $row['news_archive_id'] === 0) {
                    $warningText = $GLOBALS['TL_LANG']['tl_blog_sync_config']['noArchiveWarning'] ?? 'Kein Nachrichtenarchiv verknüpft';
                    $warning = sprintf(
                        ' <span style="color:#c33;font-weight:normal;font-size:0.85em" title="%s">&#9888; %s</span>',
                        htmlspecialchars($warningText),
                        htmlspecialchars($warningText)
                    );
                }

                // Letzten Log-Eintrag anzeigen
                $logHtml = '';
                try {
                    $lastLog = \Contao\Database::getInstance()
                        ->prepare("SELECT * FROM tl_blog_sync_log WHERE pid=? ORDER BY tstamp DESC LIMIT 1")
                        ->execute((int) $row['id'])
                        ->fetchAssoc();

                    if ($lastLog) {
                        $logColor = ($lastLog['status'] === 'success') ? '#4caf50' : '#f44336';
                        $logDate  = date('d.m.Y H:i', (int) $lastLog['tstamp']);
                        $logHtml  = sprintf(
                            ' <small style="color:%s;font-weight:normal">(%s · %s · %d importiert)</small>',
                            $logColor,
                            $logDate,
                            htmlspecialchars((string) $lastLog['sync_type']),
                            (int) $lastLog['imported_count']
                        );
                    }
                } catch (\Exception) {
                    // Log-Tabelle existiert noch nicht → ignorieren
                }

                return sprintf(
                    '<span style="font-weight:600"><span style="color:%s">%s</span> %s</span>%s%s',
                    $statusColor,
                    $status,
                    htmlspecialchars($displayName),
                    $warning,
                    $logHtml
                );
            },
        ],
        'global_operations' => [
            'connect_new' => [
                'label'           => &$GLOBALS['TL_LANG']['tl_blog_sync_config']['connect_new'],
                'href'            => '',
                'class'           => 'header_new',
                'button_callback' => static function (): string {
                    $frontendUrl = blogSyncGetFrontendUrl();
                    $connectUrl  = rtrim($frontendUrl, '/') . '/connect/contao';

                    $callbackUrl = \Contao\Environment::get('base') . 'contao/blog-sync/callback';
                    $siteUrl     = \Contao\Environment::get('host');

                    $url = $connectUrl . '?' . http_build_query([
                        'callback_url' => $callbackUrl,
                        'site_url'     => $siteUrl,
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
                'href'       => 'act=select',
                'class'      => 'header_edit_all',
                'attributes' => 'onclick="Backend.getScrollOffset()" accesskey="e"',
            ],
        ],
        'operations' => [
            'edit' => [
                'href' => 'act=edit',
                'icon' => 'edit.svg',
            ],
            'logs' => [
                'label' => &$GLOBALS['TL_LANG']['tl_blog_sync_config']['logs'],
                'href'  => 'table=tl_blog_sync_log',
                'icon'  => 'show.svg',
            ],
            'delete' => [
                'href'       => 'act=delete',
                'icon'       => 'delete.svg',
                'attributes' => 'onclick="if(!confirm(\'Soll dieser Account wirklich gelöscht werden? Die Verbindung wird auch im Agency Powerstack Backend entfernt.\'))return false;Backend.getScrollOffset()"',
            ],
        ],
    ],
    'palettes' => [
        'default' => '{sync_legend},news_archive_id,sync_enabled;{status_legend},last_sync;{api_legend},connection_id,account_email,site_url,api_key',
    ],
    'fields' => [
        'id' => [
            'sql' => "int(10) unsigned NOT NULL auto_increment",
        ],
        'tstamp' => [
            'sql' => "int(10) unsigned NOT NULL default 0",
        ],
        'connection_id' => [
            'label'     => &$GLOBALS['TL_LANG']['tl_blog_sync_config']['connection_id'],
            'exclude'   => true,
            'inputType' => 'text',
            'eval'      => ['readonly' => true, 'maxlength' => 255, 'tl_class' => 'w50'],
            'sql'       => "varchar(255) NOT NULL default ''",
        ],
        'account_email' => [
            'label'     => &$GLOBALS['TL_LANG']['tl_blog_sync_config']['account_email'],
            'exclude'   => true,
            'inputType' => 'text',
            'eval'      => ['readonly' => true, 'maxlength' => 255, 'tl_class' => 'w50'],
            'sql'       => "varchar(255) NOT NULL default ''",
        ],
        'api_key' => [
            'label'     => &$GLOBALS['TL_LANG']['tl_blog_sync_config']['api_key'],
            'exclude'   => true,
            'inputType' => 'text',
            'eval'      => ['readonly' => true, 'maxlength' => 64, 'tl_class' => 'w50'],
            'sql'       => "varchar(64) NOT NULL default ''",
        ],
        'site_url' => [
            'label'     => &$GLOBALS['TL_LANG']['tl_blog_sync_config']['site_url'],
            'exclude'   => true,
            'inputType' => 'text',
            'eval'      => ['readonly' => true, 'maxlength' => 500, 'tl_class' => 'w50'],
            'sql'       => "varchar(500) NOT NULL default ''",
        ],
        'news_archive_id' => [
            'label'      => &$GLOBALS['TL_LANG']['tl_blog_sync_config']['news_archive_id'],
            'exclude'    => true,
            'inputType'  => 'select',
            'foreignKey' => 'tl_news_archive.title',
            'eval'       => ['mandatory' => true, 'tl_class' => 'w50', 'includeBlankOption' => true],
            'sql'        => "int(10) unsigned NOT NULL default 0",
        ],
        'sync_enabled' => [
            'label'     => &$GLOBALS['TL_LANG']['tl_blog_sync_config']['sync_enabled'],
            'exclude'   => true,
            'inputType' => 'checkbox',
            'eval'      => ['tl_class' => 'w50'],
            'sql'       => "char(1) NOT NULL default ''",
        ],
        'last_sync' => [
            'label'     => &$GLOBALS['TL_LANG']['tl_blog_sync_config']['last_sync'],
            'exclude'   => true,
            'inputType' => 'text',
            'eval'      => ['readonly' => true, 'rgxp' => 'datim', 'tl_class' => 'w50'],
            'sql'       => "int(10) unsigned NOT NULL default 0",
        ],
    ],
];
