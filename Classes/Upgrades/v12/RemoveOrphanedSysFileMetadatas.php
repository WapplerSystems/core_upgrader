<?php

declare(strict_types=1);


namespace TYPO3\CMS\v12\Core\Upgrades;

use Doctrine\DBAL\Exception;
use Symfony\Component\Console\Output\OutputInterface;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Upgrades\ChattyInterface;
use TYPO3\CMS\Core\Upgrades\Confirmation;
use TYPO3\CMS\Core\Upgrades\UpgradeWizardInterface;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Attribute\UpgradeWizard;

/**
 */
#[UpgradeWizard('removeOrphanedSysFileMetadatas')]
final class RemoveOrphanedSysFileMetadatas implements UpgradeWizardInterface, ChattyInterface
{

    /**
     * @var OutputInterface
     */
    protected $output;

    /**
     * @var Confirmation
     */
    protected $confirmation;


    public function __construct()
    {
        /*
        $this->confirmation = new Confirmation(
            'Are you sure?',
            'Warning: This will remove all orphaned sys_file_metadata records from the database. Please confirm.',
            true
        );*/
    }

    public function setOutput(OutputInterface $output): void
    {
        $this->output = $output;
    }

    /**
     * Return the speaking name of this wizard
     *
     * @return string
     */
    public function getTitle(): string
    {
        return 'Removes orphaned sys_file_metadata records';
    }

    /**
     * Return the description for this wizard
     *
     * @return string
     */
    public function getDescription(): string
    {
        return 'Removes orphaned sys_file_metadata records from the database';
    }


    /**
     * Is an update necessary?
     *
     * @return bool
     * @throws Exception
     */
    public function updateNecessary(): bool
    {
        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable('sys_file_metadata');
        $queryBuilder
            ->getRestrictions()
            ->removeAll();

        $rows = $queryBuilder
            ->count('sys_file_metadata.uid')
            ->from('sys_file_metadata')
            ->leftJoin(
                'sys_file_metadata',
                'sys_file',
                'f',
                $queryBuilder->expr()->eq('f.uid', $queryBuilder->quoteIdentifier('sys_file_metadata.file'))
            )
            ->where(
                $queryBuilder->expr()->isNull('f.uid')
            )
            ->setMaxResults(1)
            ->executeQuery()->fetchOne();

        return $rows > 0;
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
        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable('sys_file_metadata');
        $queryBuilder
            ->getRestrictions()
            ->removeAll();


        $uids = $queryBuilder
            ->select('sys_file_metadata.uid')
            ->from('sys_file_metadata')
            ->leftJoin(
                'sys_file_metadata',
                'sys_file',
                'f',
                $queryBuilder->expr()->eq('f.uid', $queryBuilder->quoteIdentifier('sys_file_metadata.file'))
            )
            ->where(
                $queryBuilder->expr()->isNull('f.uid')
            )
            ->executeQuery()->fetchFirstColumn();


        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable('sys_file_metadata');
        $queryBuilder
            ->getRestrictions()
            ->removeAll();
        $queryBuilder->delete('sys_file_metadata')
            ->where(
                $queryBuilder->expr()->in(
                    'uid',
                    $uids
                )
            )
            ->executeStatement();


        $this->output->writeln('Removed ' . count($uids) . ' records.');

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


}
