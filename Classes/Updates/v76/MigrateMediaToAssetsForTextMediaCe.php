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
        $updateQueryBuilder = $connection->createQueryBuilder();
        $updateQueryBuilder->update('sys_file_reference')
            ->leftJoin('sys_file_reference','tt_content','tt_content')
            ->where(
                $updateQueryBuilder->expr()->and(
                    $updateQueryBuilder->expr()->eq(
                        'tt_content.CType', $updateQueryBuilder->createNamedParameter('textmedia', \PDO::PARAM_STR)
                    ),
                    $updateQueryBuilder->expr()->gt('media', $updateQueryBuilder->createNamedParameter(0, \PDO::PARAM_INT)),
                )
            )->set('tt_content.assets', 'tt_content.media', false)
            ->set('sys_file_reference.fieldname', 'assets')
            ->set('tt_content.media', 0)
        ->executeQuery();

        return true;
    }
}
