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
 * Migrate backend shortcut urls
 */
class MigrateShortcutUrlsAgainUpdate implements UpgradeWizardInterface
{

    /**
     * @return string Unique identifier of this updater
     */
    public function getIdentifier(): string
    {
        return 'migrateShortcutUrlsAgain';
    }

    /**
     * @return string Title of this updater
     */
    public function getTitle(): string
    {
        return 'Migrate backend shortcut urls';
    }

    /**
     * @return string Longer description of this updater
     */
    public function getDescription(): string
    {
        return 'Migrate old shortcut urls to the new module urls.';
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
            ->from('sys_be_shortcuts')
            ->execute()
            ->fetchColumn(0);
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
     * Performs the database update if shortcuts are available
     *
     * @return bool
     * @throws \Doctrine\DBAL\DBALException
     */
    public function executeUpdate(): bool
    {

        $connection = GeneralUtility::makeInstance(ConnectionPool::class)->getConnectionForTable('sys_be_shortcuts');
        $queryBuilder = $connection->createQueryBuilder();
        $statement = $queryBuilder->select('uid','url')
            ->from('sys_be_shortcuts')
            ->execute();

        while ($record = $statement->fetch()) {
            $decodedUrl = urldecode($record['url']);
            $encodedUrl = str_replace(
                [
                    '/typo3/sysext/cms/layout/db_layout.php?&',
                    '/typo3/sysext/cms/layout/db_layout.php?',
                    '/typo3/file_edit.php?&',
                    // From 7.2 to 7.4
                    'mod.php',
                ],
                [
                    '/typo3/index.php?&M=web_layout&',
                    urlencode('/typo3/index.php?&M=web_layout&'),
                    '/typo3/index.php?&M=file_edit&',
                    // From 7.2 to 7.4
                    'index.php',
                ],
                $decodedUrl
            );

            $updateQueryBuilder = $connection->createQueryBuilder();
            $updateQueryBuilder->update('sys_be_shortcuts')
                ->where(
                    $updateQueryBuilder->expr()->eq(
                        'uid',
                        $updateQueryBuilder->createNamedParameter((int)$record['uid'], \PDO::PARAM_INT)
                    )
                )->set('url', $encodedUrl)->execute();

        }
        return true;
    }
}
