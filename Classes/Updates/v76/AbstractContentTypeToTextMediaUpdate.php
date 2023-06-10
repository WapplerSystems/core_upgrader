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

use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Install\Updates\ConfirmableInterface;
use TYPO3\CMS\Install\Updates\Confirmation;
use TYPO3\CMS\Install\Updates\DatabaseUpdatedPrerequisite;
use TYPO3\CMS\Install\Updates\UpgradeWizardInterface;

/**
 * Migrate CTypes 'X' to 'textmedia' for extension 'frontend'
 */
abstract class AbstractContentTypeToTextMediaUpdate implements UpgradeWizardInterface, ConfirmableInterface
{

    protected $CType = '';


    /**
     * @var Confirmation
     */
    protected $confirmation;

    public function __construct()
    {
        $this->confirmation = new Confirmation(
            'Please make sure to read the following carefully:',
            $this->getDescription(),
            false,
            'Yes, I understand!',
            'Ok, I don\'t need it.'
        );
    }

    public function getConfirmation(): Confirmation
    {
        return $this->confirmation;
    }

    /**
     * @return string Title of this updater
     */
    public function getTitle(): string
    {
        return 'Migrate CType '.$this->CType.' to textmedia and move file relations from "image" to "assets"';
    }


    /**
     * @return string Longer description of this updater
     */
    public function getDescription(): string
    {
        return 'The extension "fluid_styled_content" is using a new CType, textmedia, ' .
            'which replaces the CType '.$this->CType.'. ' .
            'This update wizard migrates these old CType to the new one in the database. ' .
            'If backend groups have the explicit deny/allow flag set for any of the old CTypes, ' .
            'the according flag for the CType textmedia is set as well.';
    }



    /**
     * Checks if an update is needed
     *
     * @return bool Whether an update is needed (TRUE) or not (FALSE)
     */
    public function updateNecessary(): bool
    {

        if (
            !ExtensionManagementUtility::isLoaded('fluid_styled_content')
        ) {
            return false;
        }

        $queryBuilder = $this->getConnectionPool()->getQueryBuilderForTable('tt_content');
        $queryBuilder->getRestrictions()->removeAll();
        $nonTextmediaCount = $queryBuilder->count('uid')->from('tt_content')
            ->where(
                $queryBuilder->expr()->eq('CType',$queryBuilder->createNamedParameter($this->CType))
            )
            ->executeQuery()
            ->fetchOne();

        return $nonTextmediaCount !== 0;
    }

    /**
     * @return string[] All new fields and tables must exist
     */
    public function getPrerequisites(): array
    {
        return [
            DatabaseUpdatedPrerequisite::class
        ];
    }

    /**
     * Performs the database update if old CTypes are available
     *
     * @return bool
     */
    public function executeUpdate(): bool
    {

        $this->updateDatabase();
        $this->updateAllowDeny();

        return true;
    }


    protected function updateDatabase() {

        $queryBuilder = $this->getConnectionPool()->getQueryBuilderForTable('tt_content');
        $queryBuilder->getRestrictions()->removeAll();
        $result = $queryBuilder->select('uid')->from('tt_content')
            ->where(
                $queryBuilder->expr()->eq('CType',$queryBuilder->createNamedParameter($this->CType))
            )
            ->executeQuery();

        while ($row = $result->fetchAssociative()) {

            $queryBuilder = $this->getConnectionPool()->getQueryBuilderForTable('sys_file_reference');
            $queryBuilder->update('sys_file_reference')
                ->where($queryBuilder->expr()->and(
                    $queryBuilder->expr()->eq('sys_file_reference.uid_foreign', $queryBuilder->createNamedParameter($row['uid'])),
                    $queryBuilder->expr()->eq('sys_file_reference.tablenames', $queryBuilder->createNamedParameter('tt_content')),
                    $queryBuilder->expr()->eq('sys_file_reference.fieldname', $queryBuilder->createNamedParameter('image'))
                ))
                ->set('sys_file_reference.fieldname', 'assets')
                ->executeStatement();

            $queryBuilder = $this->getConnectionPool()->getQueryBuilderForTable('tt_content');
            $queryBuilder->update('tt_content')
                ->where(
                    $queryBuilder->expr()->eq('uid', $queryBuilder->createNamedParameter($row['uid'])),
                )
                ->set('tt_content.CType', 'textmedia')
                ->set('tt_content.assets', 'tt_content.image', false)
                ->set('tt_content.image', 0)
                ->executeStatement();
        }

    }


    protected function updateAllowDeny() {

        // Update explicitDeny - ALLOW
        $queryBuilder = $this->getConnectionPool()->getQueryBuilderForTable('be_groups');
        $queryBuilder
            ->update('be_groups')
            ->where(
                $queryBuilder->expr()->and(
                    $queryBuilder->expr()->like('explicit_allowdeny', $queryBuilder->createNamedParameter('%tt_content:CType:'.$this->CType.':ALLOW%')),
                    $queryBuilder->expr()->notLike('explicit_allowdeny', $queryBuilder->createNamedParameter('%tt_content:CType:textmedia:ALLOW%'))
                )

            )
            ->set('explicit_allowdeny', 'CONCAT('.$queryBuilder->quoteIdentifier('explicit_allowdeny').','.$queryBuilder->quoteIdentifier(',tt_content:CType:textmedia:ALLOW').')')
            ->executeStatement();

        // Update explicitDeny - DENY
        $queryBuilder = $this->getConnectionPool()->getQueryBuilderForTable('be_groups');
        $queryBuilder
            ->update('be_groups')
            ->where(
                $queryBuilder->expr()->and(
                    $queryBuilder->expr()->like('explicit_allowdeny', $queryBuilder->createNamedParameter('%tt_content:CType:'.$this->CType.':DENY%')),
                    $queryBuilder->expr()->notLike('explicit_allowdeny', $queryBuilder->createNamedParameter('%tt_content:CType:textmedia:DENY%'))
                )

            )
            ->set('explicit_allowdeny', 'CONCAT('.$queryBuilder->quoteIdentifier('explicit_allowdeny').','.$queryBuilder->quoteIdentifier(',tt_content:CType:textmedia:DENY').')')
            ->executeStatement();


    }

    protected function getConnectionPool(): ConnectionPool
    {
        return GeneralUtility::makeInstance(ConnectionPool::class);
    }
}
