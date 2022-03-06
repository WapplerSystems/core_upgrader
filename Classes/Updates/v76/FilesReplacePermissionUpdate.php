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
use TYPO3\CMS\Install\Updates\DatabaseUpdatedPrerequisite;
use TYPO3\CMS\Install\Updates\UpgradeWizardInterface;

/**
 * Upgrade wizard which goes through all users and groups and set the "replaceFile" permission if "writeFile" is set
 */
class FilesReplacePermissionUpdate implements UpgradeWizardInterface
{

    /**
     * @return string Unique identifier of this updater
     */
    public function getIdentifier(): string
    {
        return 'filesReplacePermission';
    }

    /**
     * @return string Title of this updater
     */
    public function getTitle(): string
    {
        return 'Set the "Files:replace" permission for all BE user/groups with "Files:write" set';
    }


    /**
     * @return string Longer description of this updater
     */
    public function getDescription(): string
    {
        return 'A new file permission was introduced regarding replacing files.' .
            ' This update sets "Files:replace" for all BE users/groups with the permission "Files:write".';
    }

    /**
     * Checks whether updates are required.
     *
     * @return bool True if there are records to update
     */
    public function updateNecessary(): bool
    {
        $updateNeeded = false;
        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable('be_users');
        $notMigratedRowsCount = (bool)$queryBuilder->count('uid')
            ->from('be_users')
            ->where(
                $queryBuilder->expr()->andX(
                    $queryBuilder->expr()->like('file_permissions', $queryBuilder->createNamedParameter('%writeFile%', \PDO::PARAM_STR)),
                    $queryBuilder->expr()->notLike('file_permissions', $queryBuilder->createNamedParameter('%replaceFile%', \PDO::PARAM_STR)),
                )
            )
            ->execute()
            ->fetchColumn(0);

        if ($notMigratedRowsCount > 0) {
            $updateNeeded = true;
        }

        if (!$updateNeeded) {
            // Fetch group records where the writeFile is set and replaceFile is not
            $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable('be_groups');
            $notMigratedRowsCount = (bool)$queryBuilder->count('uid')
                ->from('be_groups')
                ->where(
                    $queryBuilder->expr()->andX(
                        $queryBuilder->expr()->like('file_permissions', $queryBuilder->createNamedParameter('%writeFile%', \PDO::PARAM_STR)),
                        $queryBuilder->expr()->notLike('file_permissions', $queryBuilder->createNamedParameter('%replaceFile%', \PDO::PARAM_STR)),
                    )
                )
                ->execute()
                ->fetchColumn(0);
            if ($notMigratedRowsCount > 0) {
                $updateNeeded = true;
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
     * Performs the accordant updates.
     *
     * @return bool Whether everything went smoothly or not
     */
    public function executeUpdate(): bool
    {

        // Iterate over users and groups table to perform permission updates
        $tablesToProcess = ['be_groups', 'be_users'];
        foreach ($tablesToProcess as $table) {

            $connection = GeneralUtility::makeInstance(ConnectionPool::class)->getConnectionForTable($table);
            $queryBuilder = $connection->createQueryBuilder();
            $statement = $queryBuilder->select('uid','file_permissions')
                ->from($table)
                ->where(
                    $queryBuilder->expr()->andX(
                        $queryBuilder->expr()->like('file_permissions', $queryBuilder->createNamedParameter('%writeFile%', \PDO::PARAM_STR)),
                        $queryBuilder->expr()->notLike('file_permissions', $queryBuilder->createNamedParameter('%replaceFile%', \PDO::PARAM_STR)),
                    )
                )
                ->execute();
            while ($singleRecord = $statement->fetch()) {
                $updateQueryBuilder = $connection->createQueryBuilder();
                $updateQueryBuilder->update($table)
                    ->where(
                        $updateQueryBuilder->expr()->eq(
                            'uid',
                            $updateQueryBuilder->createNamedParameter((int)$singleRecord['uid'], \PDO::PARAM_INT)
                        )
                    )->set('file_permissions', $singleRecord['file_permissions'] . ',replaceFile')
                    ->execute();
            }
        }
        return true;
    }

}
