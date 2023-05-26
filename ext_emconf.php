<?php

/***************************************************************
 * Extension Manager/Repository config file for ext "core_upgrader".
 *
 * Auto generated 21-02-2021 18:57
 *
 * Manual updates:
 * Only the data in the array - everything else is removed by next
 * writing. "version" and "dependencies" must not be touched!
 ***************************************************************/

$EM_CONF[$_EXTKEY] = [
  'title' => 'Core upgrader',
  'description' => 'Run upgrade wizards for multiple TYPO3 versions at once',
  'category' => 'cli',
  'author' => 'Nicole Cordes',
  'author_email' => 'typo3@cordes.co',
  'author_company' => 'biz-design',
  'state' => 'stable',
  'uploadfolder' => 0,
  'createDirs' => '',
  'clearcacheonload' => 0,
  'version' => '12.0.0',
  'constraints' => 
  [
    'depends' => 
    [
      'typo3' => '10.4.0-10.4.99',
    ],
    'conflicts' => 
    [
    ],
    'suggests' => 
    [
    ],
  ],
];

