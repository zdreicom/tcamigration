<?php
$EM_CONF[$_EXTKEY] = array(
    'title' => 'TCA migration',
    'description' => 'A helper to migrate TCA files.',
    'category' => 'be',
    'state' => 'alpha',
    'uploadfolder' => 0,
    'createDirs' => '',
    'clearCacheOnLoad' => 0,
    'author' => 'Philipp Gampe ',
    'author_email' => 'dev@philippgampe.info',
    'author_company' => 'zdreicom AG',
    'version' => '0.0.1',
    'constraints' => array(
        'depends' => array(
            'typo3' => '7.6.0-7.6.99',
        ),
        'conflicts' => array(),
        'suggests' => array(),
    ),
);
