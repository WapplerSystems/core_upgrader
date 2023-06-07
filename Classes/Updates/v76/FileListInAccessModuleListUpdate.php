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
 * Update module access to the file list module
 */
#[UpgradeWizard('fileListInAccessModuleList')]
class FileListInAccessModuleListUpdate implements UpgradeWizardInterface
{

    /**
     * @return string Title of this updater
     */
    public function getTitle(): string
    {
        return 'Update module access to file list module';
    }

    /**
     * @return string Longer description of this updater
     */
    public function getDescription(): string
    {
        return 'The module name of the file list module has been changed. Update the access list of all backend groups and users where this module is available.';
    }

    /**
     * @var array
     */
    protected $tableFieldArray = [
        'be_groups' => 'groupMods',
        'be_users' => 'userMods',
    ];

    /**
     * @return bool True if there are records to update
     */
    public function updateNecessary(): bool
    {

        foreach ($this->tableFieldArray as $table => $field) {
            $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable($table);
            $count = $queryBuilder->count('*')
                ->from($table)
                ->where(
                    $queryBuilder->expr()->inSet($field, $queryBuilder->createNamedParameter('file_list', \PDO::PARAM_STR))
                )
                ->executeQuery()
                ->fetchOne();

            if ($count > 0) {
                return true;
            }
        }

        return false;
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
     * Performs the update
     *
     * @return bool
     */
    public function executeUpdate(): bool
    {

        foreach ($this->tableFieldArray as $table => $field) {

            $connection = GeneralUtility::makeInstance(ConnectionPool::class)->getConnectionForTable($table);
            $queryBuilder = $connection->createQueryBuilder();
            $statement = $queryBuilder->select('uid', $field)
                ->from($table)
                ->where(
                    $queryBuilder->expr()->inSet($field, $queryBuilder->createNamedParameter('file_list', \PDO::PARAM_STR))
                )
                ->executeQuery();
            while ($row = $statement->fetchAssociative()) {
                $moduleList = explode(',', $row[$field]);
                $moduleList = array_combine($moduleList, $moduleList);
                $moduleList['file_list'] = 'file_FilelistList';
                unset($moduleList['file']);

                $updateQueryBuilder = $connection->createQueryBuilder();
                $updateQueryBuilder->update($table)
                    ->where(
                        $updateQueryBuilder->expr()->eq(
                            'uid',
                            $updateQueryBuilder->createNamedParameter((int)$row['uid'], \PDO::PARAM_INT)
                        )
                    )
                    ->set($field, implode(',', $moduleList))->executeQuery();

            }
        }
        return true;
    }
}
