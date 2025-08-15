<?php

declare(strict_types=1);


namespace TYPO3\CMS\v12\Install\Updates;

use Doctrine\DBAL\Exception;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\QueryBuilder;
use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Install\Attribute\UpgradeWizard;
use TYPO3\CMS\Install\Updates\UpgradeWizardInterface;

/**
 */
#[UpgradeWizard('removeDuplicateGomapsextMapAddressMms')]
final class RemoveDuplicateGomapsextMapAddressMms implements UpgradeWizardInterface
{


    /**
     * Return the speaking name of this wizard
     *
     * @return string
     */
    public function getTitle(): string
    {
        return 'Removes duplicate tx_gomapsext_map_address_mm records from the database';
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
        $connection = GeneralUtility::makeInstance(ConnectionPool::class)->getConnectionForTable('tx_gomapsext_map_address_mm');

        /** @var QueryBuilder $queryBuilder */
        $queryBuilder = $connection->createQueryBuilder();
        $statement = $queryBuilder->count('*')
            ->addSelect('uid_local', 'uid_foreign', 'sorting', 'sorting_foreign')
            ->from('tx_gomapsext_map_address_mm')
            ->groupBy('uid_local', 'uid_foreign', 'sorting', 'sorting_foreign')
            ->having('COUNT(*) > 1')
            ->executeQuery();

        foreach ($statement->fetchAllAssociative() as $record) {

            $queryBuilder = $connection->createQueryBuilder();
            $queryBuilder->delete('tx_gomapsext_map_address_mm')
                ->where(
                    $queryBuilder->expr()->eq(
                        'uid_local',
                        $queryBuilder->createNamedParameter($record['uid_local'], Connection::PARAM_INT)
                    ),
                    $queryBuilder->expr()->eq(
                        'uid_foreign',
                        $queryBuilder->createNamedParameter($record['uid_foreign'], Connection::PARAM_INT)
                    )
                )
                ->executeStatement();


            $queryBuilder = $connection->createQueryBuilder();
            $queryBuilder->insert('tx_gomapsext_map_address_mm')
                ->values([
                    'uid_local' => $record['uid_local'],
                    'uid_foreign' => $record['uid_foreign'],
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
     * @return bool
     * @throws Exception
     */
    public function updateNecessary(): bool
    {

        if (ExtensionManagementUtility::isLoaded('go_maps_ext') === false) {
            return false;
        }

        $connection = GeneralUtility::makeInstance(ConnectionPool::class)->getConnectionForTable('tx_gomapsext_map_address_mm');

        /** @var QueryBuilder $queryBuilder */
        $queryBuilder = $connection->createQueryBuilder();
        $statement = $queryBuilder->count('*')
            ->addSelect('uid_local', 'uid_foreign', 'sorting', 'sorting_foreign')
            ->from('tx_gomapsext_map_address_mm')
            ->groupBy('uid_local', 'uid_foreign', 'sorting', 'sorting_foreign')
            ->having('COUNT(*) > 1')
            ->executeQuery();
        return $statement->rowCount() > 0;
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


}
