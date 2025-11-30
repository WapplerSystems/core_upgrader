<?php

declare(strict_types=1);


namespace TYPO3\CMS\v12\Core\Upgrades;

use Doctrine\DBAL\Exception;
use Doctrine\DBAL\ParameterType;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Upgrades\Confirmation;
use TYPO3\CMS\Core\Upgrades\UpgradeWizardInterface;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Attribute\UpgradeWizard;

/**
 */
#[UpgradeWizard('removeOrphanedSysCategoryMMRecords')]
final class RemoveOrphanedSysCategoryMMRecords implements UpgradeWizardInterface
{


    /**
     * @var Confirmation
     */
    protected $confirmation;


    /**
     * Return the speaking name of this wizard
     *
     * @return string
     */
    public function getTitle(): string
    {
        return 'Remove orphaned sys_category_record_mm records';
    }

    /**
     * Return the description for this wizard
     *
     * @return string
     */
    public function getDescription(): string
    {
        return 'Warning: Only do this if you have created a backup and put all extensions back into operation after an upgrade.';
    }


    /**
     * Is an update necessary?
     *
     * @return bool
     * @throws Exception
     */
    public function updateNecessary(): bool
    {
        // 1. check if sys_category still exists
        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable('sys_category_record_mm');
        $queryBuilder
            ->getRestrictions()
            ->removeAll();

        $missedSysCategory = $queryBuilder
            ->count('f.uid_local')
            ->from('sys_category_record_mm','f')
            ->leftJoin(
                'f',
                'sys_category',
                'g',
                $queryBuilder->expr()->eq('g.uid', $queryBuilder->quoteIdentifier('f.uid_local'))
            )
            ->where(
                $queryBuilder->expr()->isNull('g.uid')
            )
            ->setMaxResults(1)
            ->executeQuery()->fetchOne();
        if ($missedSysCategory > 0) {
            return true;
        }

        // 2. check if foreign tables still exists
        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable('sys_category_record_mm');
        $queryBuilder
            ->getRestrictions()
            ->removeAll();
        $foreignTablenames = $queryBuilder
            ->select('tablenames')
            ->from('sys_category_record_mm')
            ->groupBy('tablenames')
            ->orderBy('tablenames')
            ->executeQuery()->fetchFirstColumn();

        foreach ($foreignTablenames as $foreignTablename) {
            if ($foreignTablename === '') {
                continue;
            }
            if (!$this->checkIfTableExists($foreignTablename)) {
                return true;
            }

            // different fields
            $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable('sys_category_record_mm');
            $queryBuilder
                ->getRestrictions()
                ->removeAll();
            $foreignFieldnames = $queryBuilder
                ->select('fieldname')
                ->from('sys_category_record_mm')
                ->where(
                    $queryBuilder->expr()->eq('tablenames', $queryBuilder->createNamedParameter($foreignTablename))
                )
                ->groupBy('fieldname')
                ->orderBy('fieldname')
                ->executeQuery()->fetchFirstColumn();

            foreach ($foreignFieldnames as $foreignFieldname) {
                // now we have  foreign table and field name(s)

                // check if foreign column is still there
                if (!$this->checkIfColumninTableExists($foreignTablename, $foreignFieldname)) {
                    return true;
                }

                $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable('sys_category_record_mm');
                $queryBuilder
                    ->getRestrictions()
                    ->removeAll();

                $orphanedForeignRecords = $queryBuilder
                    ->count('*')
                    ->from('sys_category_record_mm','f')
                    ->leftJoin(
                        'f',
                        $foreignTablename,
                        'g',
                        $queryBuilder->expr()->eq('g.uid', $queryBuilder->quoteIdentifier('f.uid_foreign'))
                    )
                    ->where(
                        $queryBuilder->expr()->and(
                            $queryBuilder->expr()->eq('f.tablenames', $queryBuilder->createNamedParameter($foreignTablename)),
                            $queryBuilder->expr()->isNull('g.uid')
                        )
                    )
                    ->setMaxResults(1)
                    ->executeQuery()->fetchOne();

                if ($orphanedForeignRecords > 0) {
                    return true;
                }

            }

        }

        return false;
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

        while ($this->updateNecessary()) {
            $this->doUpdate(10000);
        }

        return true;
    }


    private function doUpdate($limit): void {


        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable('sys_category_record_mm');
        $queryBuilder
            ->getRestrictions()
            ->removeAll();
        $rows = $queryBuilder
            ->select('f.*')
            ->setMaxResults($limit)
            ->from('sys_category_record_mm','f')
            ->leftJoin(
                'f',
                'sys_category',
                'g',
                $queryBuilder->expr()->eq('g.uid', $queryBuilder->quoteIdentifier('f.uid_local'))
            )
            ->where(
                $queryBuilder->expr()->isNull('g.uid')
            )
            ->executeQuery()->fetchAllAssociative();

        if (count($rows) > 0) {
            foreach ($rows as $row) {
                $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable('sys_category_record_mm');
                $queryBuilder
                    ->getRestrictions()
                    ->removeAll();
                $queryBuilder->delete('sys_category_record_mm')
                    ->where(
                        $queryBuilder->expr()->eq(
                            'uid_local',
                            $queryBuilder->createNamedParameter($row['uid_local'], ParameterType::INTEGER)
                        ),
                        $queryBuilder->expr()->eq(
                            'uid_foreign',
                            $queryBuilder->createNamedParameter($row['uid_foreign'], ParameterType::INTEGER)
                        ),
                        $queryBuilder->expr()->eq(
                            'tablenames',
                            $queryBuilder->createNamedParameter($row['tablenames'])
                        ),
                        $queryBuilder->expr()->eq(
                            'fieldname',
                            $queryBuilder->createNamedParameter($row['fieldname'])
                        )
                    )
                    ->executeStatement();
            }
        }

        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable('sys_category_record_mm');
        $queryBuilder
            ->getRestrictions()
            ->removeAll();
        $foreignTablenames = $queryBuilder
            ->select('tablenames')
            ->from('sys_category_record_mm')
            ->groupBy('tablenames')
            ->orderBy('tablenames')
            ->executeQuery()->fetchFirstColumn();

        foreach ($foreignTablenames as $foreignTablename) {
            if ($foreignTablename === '') {
                continue;
            }
            // 1. remove records of foreign table that do not exist
            if (!$this->checkIfTableExists($foreignTablename)) {

                $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable('sys_category_record_mm');
                $queryBuilder
                    ->getRestrictions()
                    ->removeAll();
                $queryBuilder->delete('sys_category_record_mm')
                    ->where(
                        $queryBuilder->expr()->eq(
                            'tablenames',
                            $queryBuilder->createNamedParameter($foreignTablename)
                        )
                    )
                    ->executeStatement();
                continue;
            }


            // 2. foreign column does not exist
            $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable('sys_category_record_mm');
            $queryBuilder
                ->getRestrictions()
                ->removeAll();
            $foreignFieldnames = $queryBuilder
                ->select('fieldname')
                ->from('sys_category_record_mm')
                ->where(
                    $queryBuilder->expr()->eq('tablenames', $queryBuilder->createNamedParameter($foreignTablename))
                )
                ->groupBy('fieldname')
                ->orderBy('fieldname')
                ->executeQuery()->fetchFirstColumn();


            foreach ($foreignFieldnames as $foreignFieldname) {


                if (!$this->checkIfColumninTableExists($foreignTablename, $foreignFieldname)) {

                    $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable('sys_category_record_mm');
                    $queryBuilder
                        ->getRestrictions()
                        ->removeAll();
                    $queryBuilder->delete('sys_category_record_mm')
                        ->where(
                            $queryBuilder->expr()->eq(
                                'tablenames',
                                $queryBuilder->createNamedParameter($foreignTablename)
                            ),
                            $queryBuilder->expr()->eq(
                                'fieldname',
                                $queryBuilder->createNamedParameter($foreignFieldname)
                            )
                        )
                        ->executeStatement();
                }

            }


            // 3. remove records that do not have a foreign record
            $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable('sys_category_record_mm');
            $queryBuilder
                ->getRestrictions()
                ->removeAll();
            $orphanedForeignRecords = $queryBuilder
                ->select('f.*')
                ->setMaxResults($limit)
                ->from('sys_category_record_mm','f')
                ->leftJoin(
                    'f',
                    $foreignTablename,
                    'g',
                    $queryBuilder->expr()->eq('g.uid', $queryBuilder->quoteIdentifier('f.uid_foreign'))
                )
                ->where(
                    $queryBuilder->expr()->and(
                        $queryBuilder->expr()->eq('f.tablenames', $queryBuilder->createNamedParameter($foreignTablename)),
                        $queryBuilder->expr()->isNull('g.uid')
                    )
                )
                ->executeQuery()->fetchAllAssociative();


            if (count($orphanedForeignRecords) > 0) {
                foreach ($orphanedForeignRecords as $row) {

                    $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable('sys_category_record_mm');
                    $queryBuilder
                        ->getRestrictions()
                        ->removeAll();
                    $queryBuilder->delete('sys_category_record_mm')
                        ->where(
                            $queryBuilder->expr()->eq(
                                'uid_local',
                                $queryBuilder->createNamedParameter($row['uid_local'], ParameterType::INTEGER)
                            ),
                            $queryBuilder->expr()->eq(
                                'uid_foreign',
                                $queryBuilder->createNamedParameter($row['uid_foreign'], ParameterType::INTEGER)
                            ),
                            $queryBuilder->expr()->eq(
                                'tablenames',
                                $queryBuilder->createNamedParameter($row['tablenames'])
                            ),
                            $queryBuilder->expr()->eq(
                                'fieldname',
                                $queryBuilder->createNamedParameter($row['fieldname'])
                            )
                        )
                        ->executeStatement();
                }
            }

        }


    }



    /**
     *
     * @return string[]
     */
    public function getPrerequisites(): array
    {
        return [
        ];
    }


    /**
     * Check if given table exists
     *
     * @param string $table
     * @return bool
     * @throws Exception
     */
    protected function checkIfTableExists($table): bool
    {
        $tableExists = GeneralUtility::makeInstance(ConnectionPool::class)
            ->getConnectionForTable($table)
            ->createSchemaManager()
            ->tablesExist([$table]);

        return $tableExists;
    }

    /**
     * Check if given table exists
     *
     * @param string $table
     * @return bool
     * @throws Exception
     */
    protected function checkIfColumninTableExists($table, $column): bool
    {
        if (!$this->checkIfTableExists($table)) {
            return false;
        }
        $tableColumns = GeneralUtility::makeInstance(ConnectionPool::class)
            ->getConnectionForTable($table)
            ->createSchemaManager()
            ->listTableColumns($table);
        return isset($tableColumns[$column]);
    }


}
