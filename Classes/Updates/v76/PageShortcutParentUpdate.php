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
use TYPO3\CMS\Core\Domain\Repository\PageRepository;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Install\Attribute\UpgradeWizard;
use TYPO3\CMS\Install\Updates\DatabaseUpdatedPrerequisite;
use TYPO3\CMS\Install\Updates\UpgradeWizardInterface;


/**
 * Update all pages which have set the shortcut mode "Parent of selected or current page" (PageRepository::SHORTCUT_MODE_PARENT_PAGE)
 * to remove a possibly selected page as this would cause a different behaviour of the shortcut now
 * since the selected page is now respected in this shortcut mode.
 */
#[UpgradeWizard('pageShortcutParent')]
class PageShortcutParentUpdate implements UpgradeWizardInterface
{

    /**
     * @return string Title of this updater
     */
    public function getTitle(): string
    {
        return 'Update page shortcuts with shortcut type "Parent of selected or current page"';
    }

    /**
     * @return string Longer description of this updater
     */
    public function getDescription(): string
    {
        return 'There are some shortcut pages that need to updated in order to preserve their current behaviour.';
    }


    /**
     * Checks if an update is needed
     *
     * @return bool Whether an update is needed (TRUE) or not (FALSE)
     */
    public function updateNecessary(): bool
    {

        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable('pages');
        return (bool)$queryBuilder->count('uid')
            ->from('pages')
            ->where(
                $queryBuilder->expr()->neq('shortcut', $queryBuilder->createNamedParameter(0, \PDO::PARAM_INT)),
                $queryBuilder->expr()->eq('shortcut_mode', $queryBuilder->createNamedParameter(PageRepository::SHORTCUT_MODE_PARENT_PAGE, \PDO::PARAM_STR))
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
     * Performs the database update
     *
     * @return bool
     */
    public function executeUpdate(): bool
    {
        $updateQueryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable('pages');
        $updateQueryBuilder->update('pages')
            ->where(
                $updateQueryBuilder->expr()->neq('shortcut', $updateQueryBuilder->createNamedParameter(0, \PDO::PARAM_INT)),
                $updateQueryBuilder->expr()->eq('shortcut_mode', $updateQueryBuilder->createNamedParameter(PageRepository::SHORTCUT_MODE_PARENT_PAGE, \PDO::PARAM_STR))
            )->set('shortcut', 0)->executeQuery();

        return true;
    }
}
