<?php

declare(strict_types=1);

/***************************************************************
 * Extension Manager/Repository config file for ext "core_upgrader2".
 *
 * Auto generated 21-02-2021 18:57
 *
 * Manual updates:
 * Only the data in the array - everything else is removed by next
 * writing. "version" and "dependencies" must not be touched!
 ***************************************************************/
$EM_CONF[$_EXTKEY] = [
    'title' => 'Core upgrader',
    'description' => 'Run upgrade wizards for multiple TYPO3 versions at once and clean up the system',
    'category' => 'cli',
    'author' => 'Sven Wappler',
    'author_email' => 'typo3@wappler.systems',
    'author_company' => 'WapplerSystems',
    'state' => 'stable',
    'version' => '13.0.1',
    'constraints' => [
        'depends' => [
            'typo3' => '14.0.0-14.3.99',
        ],
    ],
];
