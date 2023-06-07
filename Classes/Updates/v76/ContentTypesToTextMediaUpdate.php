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

use Doctrine\DBAL\ArrayParameterType;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Install\Attribute\UpgradeWizard;
use TYPO3\CMS\Install\Updates\DatabaseUpdatedPrerequisite;
use TYPO3\CMS\Install\Updates\UpgradeWizardInterface;

/**
 * Migrate CTypes 'text', 'image' and 'textpic' to 'textmedia' for extension 'frontend'
 */
#[UpgradeWizard('contentTypesToTextMedia')]
class ContentTypesToTextMediaUpdate implements UpgradeWizardInterface
{

    private const TABLE_NAME = 'tt_content';


    /**
     * @return string Title of this updater
     */
    public function getTitle(): string
    {
        return 'Migrate CTypes text, image and textpic to textmedia and move file relations from "image" to "asset_references"';
    }


    /**
     * @return string Longer description of this updater
     */
    public function getDescription(): string
    {
        return 'The extension "fluid_styled_content" is using a new CType, textmedia, ' .
            'which replaces the CTypes text, image and textpic. ' .
            'This update wizard migrates these old CTypes to the new one in the database. ' .
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
        $updateNeeded = true;

        if (
            !ExtensionManagementUtility::isLoaded('fluid_styled_content')
        ) {
            $updateNeeded = false;
        } else {
            $queryBuilder = $this->getConnectionPool()->getQueryBuilderForTable('tt_content');
            $queryBuilder->getRestrictions()->removeAll();
            $nonTextmediaCount = $queryBuilder->count('uid')->from('tt_content')
                ->where(
                    $queryBuilder->expr()->in('CType',$queryBuilder->createNamedParameter(['text','image','textpic'], ArrayParameterType::INTEGER))
                )
                ->executeQuery()
                ->fetchOne();

            if ($nonTextmediaCount === 0) {
                $updateNeeded = false;
            }
        }

        return $updateNeeded;
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

        // Update 'text' records
        $databaseConnection->exec_UPDATEquery(
            'tt_content',
            'tt_content.CType=' . $databaseConnection->fullQuoteStr('text', 'tt_content'),
            [
                'CType' => 'textmedia',
            ]
        );

        // Store last executed query
        $databaseQueries[] = str_replace(chr(10), ' ', $databaseConnection->debug_lastBuiltQuery);
        // Check for errors
        if ($databaseConnection->sql_error()) {
            $customMessages = 'SQL-ERROR: ' . htmlspecialchars($databaseConnection->sql_error());
            return false;
        }

        // Update 'textpic' and 'image' records
        $query = '
            UPDATE tt_content
            LEFT JOIN sys_file_reference
            ON sys_file_reference.uid_foreign=tt_content.uid
            AND sys_file_reference.tablenames=' . $databaseConnection->fullQuoteStr('tt_content', 'sys_file_reference')
            . ' AND sys_file_reference.fieldname=' . $databaseConnection->fullQuoteStr('image', 'sys_file_reference')
            . ' SET tt_content.CType=' . $databaseConnection->fullQuoteStr('textmedia', 'tt_content')
            . ', tt_content.assets=tt_content.image,
            tt_content.image=0,
            sys_file_reference.fieldname=' . $databaseConnection->fullQuoteStr('assets', 'tt_content')
            . ' WHERE
            tt_content.CType=' . $databaseConnection->fullQuoteStr('textpic', 'tt_content')
            . ' OR tt_content.CType=' . $databaseConnection->fullQuoteStr('image', 'tt_content');
        $databaseConnection->sql_query($query);

        // Store last executed query
        $databaseQueries[] = str_replace(chr(10), ' ', $query);
        // Check for errors
        if ($databaseConnection->sql_error()) {
            $customMessages = 'SQL-ERROR: ' . htmlspecialchars($databaseConnection->sql_error());
            return false;
        }

        // Update explicitDeny - ALLOW
        $databaseConnection->exec_UPDATEquery(
            'be_groups',
            '(explicit_allowdeny LIKE ' . $databaseConnection->fullQuoteStr('%' . $databaseConnection->escapeStrForLike('tt_content:CType:textpic:ALLOW', 'tt_content') . '%', 'tt_content')
                . ' OR explicit_allowdeny LIKE ' . $databaseConnection->fullQuoteStr('%' . $databaseConnection->escapeStrForLike('tt_content:CType:image:ALLOW', 'tt_content') . '%', 'tt_content')
                . ' OR explicit_allowdeny LIKE ' . $databaseConnection->fullQuoteStr('%' . $databaseConnection->escapeStrForLike('tt_content:CType:text:ALLOW', 'tt_content') . '%', 'tt_content')
                . ') AND explicit_allowdeny NOT LIKE ' . $databaseConnection->fullQuoteStr('%' . $databaseConnection->escapeStrForLike('tt_content:CType:textmedia:ALLOW', 'tt_content') . '%', 'tt_content'),
            [
                'explicit_allowdeny' => 'CONCAT(explicit_allowdeny,' . $databaseConnection->fullQuoteStr(',tt_content:CType:textmedia:ALLOW', 'tt_content') . ')',
            ],
            [
                'explicit_allowdeny',
            ]
        );

        // Store last executed query
        $databaseQueries[] = str_replace(chr(10), ' ', $databaseConnection->debug_lastBuiltQuery);

        // Update explicitDeny - DENY
        $databaseConnection->exec_UPDATEquery(
            'be_groups',
            '(explicit_allowdeny LIKE ' . $databaseConnection->fullQuoteStr('%' . $databaseConnection->escapeStrForLike('tt_content:CType:textpic:DENY', 'tt_content') . '%', 'tt_content')
                . ' OR explicit_allowdeny LIKE ' . $databaseConnection->fullQuoteStr('%' . $databaseConnection->escapeStrForLike('tt_content:CType:image:DENY', 'tt_content') . '%', 'tt_content')
                . ' OR explicit_allowdeny LIKE ' . $databaseConnection->fullQuoteStr('%' . $databaseConnection->escapeStrForLike('tt_content:CType:text:DENY', 'tt_content') . '%', 'tt_content')
                . ') AND explicit_allowdeny NOT LIKE ' . $databaseConnection->fullQuoteStr('%' . $databaseConnection->escapeStrForLike('tt_content:CType:textmedia:DENY', 'tt_content') . '%', 'tt_content'),
            [
                'explicit_allowdeny' => 'CONCAT(explicit_allowdeny,' . $databaseConnection->fullQuoteStr(',tt_content:CType:textmedia:DENY', 'tt_content') . ')',
            ],
            [
                'explicit_allowdeny',
            ]
        );


        return true;
    }


    protected function getConnectionPool(): ConnectionPool
    {
        return GeneralUtility::makeInstance(ConnectionPool::class);
    }
}
