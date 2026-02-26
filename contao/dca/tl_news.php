<?php

/**
 * Erweiterung der tl_news Tabelle für externe ID
 */
$GLOBALS['TL_DCA']['tl_news']['fields']['externalId'] = [
    'label'     => ['Externe ID', 'ID aus dem Agency Powerstack System'],
    'exclude'   => true,
    'inputType' => 'text',
    'eval'      => ['maxlength' => 255, 'tl_class' => 'w50'],
    'sql'       => "varchar(255) NOT NULL default ''",
];
