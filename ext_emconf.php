<?php

/***************************************************************
 * Extension Manager/Repository config file for ext "core_upgrader2".
 *
 * Auto generated 21-02-2021 18:57
 *
 * Manual updates:
 * Only the data in the array - everything else is removed by next
 * writing. "version" and "dependencies" must not be touched!
 ***************************************************************/

$EM_CONF['core_upgrader2'] = [
    'title' => 'Core upgrader',
    'description' => 'Run upgrade wizards for multiple TYPO3 versions at once and clean up the system',
    'category' => 'cli',
    'author' => 'Sven Wappler',
    'author_email' => 'typo3@wappler.systems',
    'author_company' => 'WapplerSystems',
    'state' => 'stable',
    'version' => '13.0.0',
    'constraints' => [
        'depends' => [
            'typo3' => '13.4.0-13.4.99',
        ],
    ],
];

