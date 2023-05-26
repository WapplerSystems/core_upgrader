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
use TYPO3\CMS\Install\Attribute\Operation;
use TYPO3\CMS\Install\Updates\DatabaseUpdatedPrerequisite;
use TYPO3\CMS\Install\Updates\UpgradeWizardInterface;

/**
 * Update backend user setting startModule if set to "help_aboutmodules"
 */
#[Operation('backendUserStartModule')]
class BackendUserStartModuleUpdate implements UpgradeWizardInterface
{

    /**
     * @return string Unique identifier of this updater
     */
    public function getIdentifier(): string
    {
        return 'backendUserStartModule';
    }

    /**
     * @return string Title of this updater
     */
    public function getTitle(): string
    {
        return 'Update backend user setting "startModule"';
    }

    /**
     * @return string Longer description of this updater
     */
    public function getDescription(): string
    {
        return 'The backend user setting startModule is changed for the extension aboutmodules. Update all backend users that use ext:aboutmodules as startModule.';
    }

    /**
     * Checks if an update is needed
     *
     * @return bool Whether an update is needed (TRUE) or not (FALSE)
     */
    public function updateNecessary(): bool
    {
        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable('sys_refindex');
        return (bool)$queryBuilder->count('uid')
            ->from('be_users')
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
     * Performs the database update if backend user's startmodule is help_aboutmodules
     *
     * @return bool
     */
    public function executeUpdate(): bool
    {
        $connection = GeneralUtility::makeInstance(ConnectionPool::class)->getConnectionForTable('be_users');
        $queryBuilder = $connection->createQueryBuilder();
        $statement = $queryBuilder->select('uid','uc')
            ->from('be_users')
            ->executeQuery();
        while ($backendUser = $statement->fetchAssociative()) {

            if ($backendUser['uc'] !== null) {
                $userConfig = unserialize($backendUser['uc']);
                if ($userConfig['startModule'] === 'help_aboutmodules') {
                    $userConfig['startModule'] = 'help_AboutmodulesAboutmodules';

                    $updateQueryBuilder = $connection->createQueryBuilder();
                    $updateQueryBuilder->update('be_users')
                        ->where(
                            $updateQueryBuilder->expr()->eq(
                                'uid',
                                $updateQueryBuilder->createNamedParameter((int)$backendUser['uid'], \PDO::PARAM_INT)
                            )
                        )->set('uc',serialize($userConfig))->executeQuery();

                }
            }
        }

        return true;
    }
}
