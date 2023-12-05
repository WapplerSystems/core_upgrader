<?php

declare(strict_types=1);

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

namespace TYPO3\CMS\v12\Install\Updates;

use Doctrine\DBAL\Exception;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\QueryBuilder;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Install\Attribute\UpgradeWizard;
use TYPO3\CMS\Install\Updates\UpgradeWizardInterface;

/**
 */
#[UpgradeWizard('removeDuplicateSysCategoryRecordMms')]
final class RemoveDuplicateSysCategoryRecordMms implements UpgradeWizardInterface
{


    /**
     * Return the speaking name of this wizard
     *
     * @return string
     */
    public function getTitle(): string
    {
        return 'Removes duplicate sys_category_record_mm records from the database';
    }

    /**
     * Return the description for this wizard
     *
     * @return string
     */
    public function getDescription(): string
    {
        return '';
    }

    /**
     * Execute the update
     *
     * Called when a wizard reports that an update is necessary
     *
     * @return bool
     */
    public function executeUpdate(): bool
    {
        $connection = GeneralUtility::makeInstance(ConnectionPool::class)->getConnectionForTable('sys_category_record_mm');

        /** @var QueryBuilder $queryBuilder */
        $queryBuilder = $connection->createQueryBuilder();
        $statement = $queryBuilder->count('*')
            ->addSelect('uid_local', 'uid_foreign', 'tablenames', 'fieldname', 'sorting', 'sorting_foreign')
            ->from('sys_category_record_mm')
            ->groupBy('uid_local', 'uid_foreign', 'tablenames', 'fieldname')
            ->having('COUNT(*) > 1')
            ->executeQuery();

        foreach ($statement->fetchAllAssociative() as $record) {

            $queryBuilder = $connection->createQueryBuilder();
            $queryBuilder->delete('sys_category_record_mm')
                ->where(
                    $queryBuilder->expr()->eq(
                        'uid_local',
                        $queryBuilder->createNamedParameter($record['uid_local'], Connection::PARAM_INT)
                    ),
                    $queryBuilder->expr()->eq(
                        'uid_foreign',
                        $queryBuilder->createNamedParameter($record['uid_foreign'], Connection::PARAM_INT)
                    ),
                    $queryBuilder->expr()->eq(
                        'tablenames',
                        $queryBuilder->createNamedParameter($record['tablenames'])
                    ),
                    $queryBuilder->expr()->eq(
                        'fieldname',
                        $queryBuilder->createNamedParameter($record['fieldname'])
                    )
                )
                ->executeStatement();


            $queryBuilder = $connection->createQueryBuilder();
            $queryBuilder->insert('sys_category_record_mm')
                ->values([
                    'uid_local' => $record['uid_local'],
                    'uid_foreign' => $record['uid_foreign'],
                    'tablenames' => $record['tablenames'],
                    'fieldname' => $record['fieldname'],
                    'sorting' => $record['sorting'],
                    'sorting_foreign' => $record['sorting_foreign'],
                ])
                ->executeStatement();

        }
        return true;
    }

    /**
     * Is an update necessary?
     *
     * Looks for fe plugins in tt_content table to be migrated
     *
     * @return bool
     * @throws Exception
     */
    public function updateNecessary(): bool
    {


        return true;
    }

    /**
     * Returns an array of class names of Prerequisite classes
     *
     * This way a wizard can define dependencies like "database up-to-date" or
     * "reference index updated"
     *
     * @return string[]
     */
    public function getPrerequisites(): array
    {
        return [
        ];
    }


}
