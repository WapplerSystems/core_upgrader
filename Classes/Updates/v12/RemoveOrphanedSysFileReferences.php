<?php

declare(strict_types=1);


namespace TYPO3\CMS\v12\Install\Updates;

use Doctrine\DBAL\Exception;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Install\Attribute\UpgradeWizard;
use TYPO3\CMS\Install\Updates\Confirmation;
use TYPO3\CMS\Install\Updates\UpgradeWizardInterface;

/**
 */
#[UpgradeWizard('removeOrphanedSysFileReferences')]
final class RemoveOrphanedSysFileReferences implements UpgradeWizardInterface
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
        return 'Removes orphaned sys_file_references records';
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
        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable('sys_file_reference');
        $queryBuilder
            ->getRestrictions()
            ->removeAll();

        $missedSysFiles = $queryBuilder
            ->count('f.uid')
            ->from('sys_file_reference','f')
            ->leftJoin(
                'f',
                'sys_file',
                'g',
                $queryBuilder->expr()->eq('g.uid', $queryBuilder->quoteIdentifier('f.uid_local'))
            )
            ->where(
                $queryBuilder->expr()->isNull('g.uid')
            )
            ->setMaxResults(1)
            ->executeQuery()->fetchOne();
        if ($missedSysFiles > 0) {
            return true;
        }

        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable('sys_file_reference');
        $queryBuilder
            ->getRestrictions()
            ->removeAll();
        $foreignTablenames = $queryBuilder
            ->select('tablenames')
            ->from('sys_file_reference')
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

            $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable('sys_file_reference');
            $queryBuilder
                ->getRestrictions()
                ->removeAll();

            $orphanedForeignRecords = $queryBuilder
                ->count('f.uid')
                ->from('sys_file_reference','f')
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

        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable('sys_file_reference');
        $queryBuilder
            ->getRestrictions()
            ->removeAll();

        $uids = $queryBuilder
            ->select('f.uid')
            ->from('sys_file_reference','f')
            ->leftJoin(
                'f',
                'sys_file',
                'g',
                $queryBuilder->expr()->eq('g.uid', $queryBuilder->quoteIdentifier('f.uid_local'))
            )
            ->where(
                $queryBuilder->expr()->isNull('g.uid')
            )
            ->executeQuery()->fetchFirstColumn();

        if (count($uids) > 0) {
            $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable('sys_file_reference');
            $queryBuilder
                ->getRestrictions()
                ->removeAll();
            $queryBuilder->delete('sys_file_reference')
                ->where(
                    $queryBuilder->expr()->in(
                        'uid',
                        $uids
                    )
                )
                ->executeStatement();
        }


        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable('sys_file_reference');
        $queryBuilder
            ->getRestrictions()
            ->removeAll();
        $foreignTablenames = $queryBuilder
            ->select('tablenames')
            ->from('sys_file_reference')
            ->groupBy('tablenames')
            ->orderBy('tablenames')
            ->executeQuery()->fetchFirstColumn();

        foreach ($foreignTablenames as $foreignTablename) {
            if ($foreignTablename === '') {
                continue;
            }
            if (!$this->checkIfTableExists($foreignTablename)) {

                $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable('sys_file_reference');
                $queryBuilder
                    ->getRestrictions()
                    ->removeAll();
                $queryBuilder->delete('sys_file_reference')
                    ->where(
                        $queryBuilder->expr()->eq(
                            'tablenames',
                            $queryBuilder->createNamedParameter($foreignTablename)
                        )
                    )
                    ->executeStatement();

                continue;
            }

            $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable('sys_file_reference');
            $queryBuilder
                ->getRestrictions()
                ->removeAll();

            $orphanedForeignRecordsUids = $queryBuilder
                ->select('f.uid')
                ->from('sys_file_reference','f')
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
                ->executeQuery()->fetchFirstColumn();

            if (count($orphanedForeignRecordsUids) > 0) {
                $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable('sys_file_reference');
                $queryBuilder
                    ->getRestrictions()
                    ->removeAll();
                $queryBuilder->delete('sys_file_reference')
                    ->where(
                        $queryBuilder->expr()->in(
                            'uid',
                            $orphanedForeignRecordsUids
                        )
                    )
                    ->executeStatement();
            }

        }

        return true;
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


}
