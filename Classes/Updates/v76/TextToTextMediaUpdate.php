<?php
namespace TYPO3\CMS\v76\Install\Updates;

/*
 * This file is part of the TYPO3 CMS project.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 * The TYPO3 project - inspiring people to share!
 */

use TYPO3\CMS\Install\Attribute\UpgradeWizard;

/**
 * Migrate CType 'text' to 'textmedia' for extension 'frontend'
 */
#[UpgradeWizard('textToTextMedia')]
class TextToTextMediaUpdate extends AbstractContentTypeToTextMediaUpdate
{

    protected $CType = 'text';


    public function getTitle(): string
    {
        return 'Migrate CType '.$this->CType.' to textmedia';
    }

    protected function updateDatabase() {

        $queryBuilder = $this->getConnectionPool()->getQueryBuilderForTable('tt_content');
        $queryBuilder
            ->update('tt_content')
            ->where(
                $queryBuilder->expr()->eq('CType', $queryBuilder->createNamedParameter($this->CType))
            )
            ->set('CType', 'textmedia')
            ->executeStatement();


    }


}
