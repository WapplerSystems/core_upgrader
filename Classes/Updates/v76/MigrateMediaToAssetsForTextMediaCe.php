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
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Install\Attribute\UpgradeWizard;
use TYPO3\CMS\Install\Updates\DatabaseUpdatedPrerequisite;
use TYPO3\CMS\Install\Updates\UpgradeWizardInterface;
use TYPO3\CMS\Core\Database\Query\Restriction\DeletedRestriction;

/**
 * Migrate CTypes 'textmedia' to use 'assets' field instead of 'media'
 */
#[UpgradeWizard('migrateMediaToAssetsForTextMediaCe')]
class MigrateMediaToAssetsForTextMediaCe implements UpgradeWizardInterface
{

    /**
     * @return string Title of this updater
     */
    public function getTitle(): string
    {
        return 'Migrate CTypes textmedia database field "media" to "assets"';
    }

    /**
     * @return string Longer description of this updater
     */
    public function getDescription(): string
    {
        return 'The extension "fluid_styled_content" is using a new database field for mediafile references. ' .
            'This update wizard migrates these old references to use the new database field.';
    }


    /**
     * Checks if an update is needed
     *
     * @return bool Whether an update is needed (TRUE) or not (FALSE)
     */
    public function updateNecessary(): bool
    {

        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable('tt_content');
        return (bool)$queryBuilder->count('uid')
            ->from('tt_content')
            ->where(
                $queryBuilder->expr()->and(
                    $queryBuilder->expr()->eq('CType', $queryBuilder->createNamedParameter('textmedia', \PDO::PARAM_STR)),
                    $queryBuilder->expr()->gt('media', $queryBuilder->createNamedParameter(0, \PDO::PARAM_INT)),
                )
            )
            ->executeQuery()
            ->fetchOne();
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
     * Performs the database update if old mediafile references are available
     *
     * @return bool
     */
     public function executeUpdate(): bool
     {
         $connection = GeneralUtility::makeInstance(ConnectionPool::class)->getConnectionForTable('sys_file_reference');
         $queryBuilder = $connection->createQueryBuilder();
         $queryBuilder->getRestrictions()->removeAll()->add(GeneralUtility::makeInstance(DeletedRestriction::class));
         $result = $queryBuilder
             ->select('uid_local', 'uid_foreign')
             ->from('sys_file_reference')
             ->leftJoin(
                 'sys_file_reference','tt_content','tt_content',
                 $queryBuilder->expr()->eq('sys_file_reference.uid_foreign', $queryBuilder->quoteIdentifier('tt_content.uid'))
             )
             ->where(
                 $queryBuilder->expr()->and(
                     $queryBuilder->expr()->eq(
                         'tt_content.CType', $queryBuilder->createNamedParameter('textmedia', \PDO::PARAM_STR)
                     ),
                     $queryBuilder->expr()->gt('tt_content.media', $queryBuilder->createNamedParameter(0, \PDO::PARAM_INT)),
                     $queryBuilder->expr()->eq('sys_file_reference.tablenames', $queryBuilder->createNamedParameter('tt_content', \PDO::PARAM_STR)),
                 )
             )
             ->executeQuery();

         $ttContentUids = [];
         $updateQueryBuilder = $connection->createQueryBuilder();
         while ($row = $result->fetchAssociative()) {
             $ttContentUids[] = $row['uid_foreign'];
             $updateQueryBuilder->update('sys_file_reference')
                 ->set('fieldname', 'assets')
                 ->where(
                     $updateQueryBuilder->expr()->and(
                         $updateQueryBuilder->expr()->eq('tablenames', $updateQueryBuilder->createNamedParameter('tt_content', \PDO::PARAM_STR)),
                         $updateQueryBuilder->expr()->eq('uid_local', $updateQueryBuilder->createNamedParameter($row['uid_local'], \PDO::PARAM_INT)),
                         $updateQueryBuilder->expr()->eq('uid_foreign', $updateQueryBuilder->createNamedParameter($row['uid_foreign'], \PDO::PARAM_INT)),
                     )
                 )
                 ->executeStatement();
         }

         if (!empty($ttContentUids)) {
           $updateQueryBuilder->resetQueryParts();
           $updateQueryBuilder->update('tt_content')
               ->set('assets', 'media', false)
               ->set('media', 0)
               ->where($updateQueryBuilder->expr()->in('uid', $ttContentUids))
               ->executeStatement();
         }

         return true;
     }
}
