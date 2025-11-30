<?php
namespace TYPO3\CMS\v76\Core\Upgrades;

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

use Doctrine\DBAL\Exception;
use Doctrine\DBAL\ParameterType;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Upgrades\DatabaseUpdatedPrerequisite;
use TYPO3\CMS\Core\Upgrades\UpgradeWizardInterface;
use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Attribute\UpgradeWizard;

/**
 * Update sys_language records to use the newly created
 * field language_isocode, if they have used the now deprecated
 * static_lang_isocode
 */
#[UpgradeWizard('languageIsoCode')]
class LanguageIsoCodeUpdate implements UpgradeWizardInterface
{

    /**
     * @return string Title of this updater
     */
    public function getTitle(): string
    {
        return 'Update sys_language records to use new ISO 639-1 letter-code field';
    }


    /**
     * @return string Longer description of this updater
     */
    public function getDescription(): string
    {
        return 'The sys_language records have a new iso code field which removes the dependency of the TYPO3 CMS Core to the extension "static_info_tables". This upgrade wizard migrates the data of the existing "static_lang_isocode" field to the new DB field.';
    }


    /**
     * Checks if an update is needed
     *
     * @return bool Whether an update is needed (TRUE) or not (FALSE)
     */
    public function updateNecessary(): bool
    {
        if (!ExtensionManagementUtility::isLoaded('static_info_tables')) {
            return false;
        }

        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable('sys_language');


        $migratableLanguageRecordsCount = $queryBuilder
            ->count('uid')
            ->from('sys_language')
            ->where(
                $queryBuilder->expr()->eq('language_isocode', $queryBuilder->createNamedParameter('')),
                $queryBuilder->expr()->literal('CAST(static_lang_isocode AS CHAR) != \'\'')
            )
            ->executeQuery()
            ->fetchOne();

        return $migratableLanguageRecordsCount > 0;
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
     * Performs the database update if the old field "static_lang_isocode"
     * is in use and populates the new field "language_isocode" with the
     * data of the old relation.
     *
     * @return bool
     * @throws Exception
     */
    public function executeUpdate(): bool
    {
        $connectionPool = GeneralUtility::makeInstance(ConnectionPool::class);
        $queryBuilderSysLanguage = $connectionPool->getQueryBuilderForTable('sys_language');

        $emptyValue = $queryBuilderSysLanguage->createNamedParameter('');

        $migrateableLanguageRecords = $queryBuilderSysLanguage
            ->select('uid', 'static_lang_isocode')
            ->from('sys_language')
            ->where(
                $queryBuilderSysLanguage->expr()->eq('language_isocode', $emptyValue),
                $queryBuilderSysLanguage->expr()->literal('CAST(static_lang_isocode AS CHAR) != \'\'')
            )
            ->executeQuery()
            ->fetchAllAssociative();

        foreach ($migrateableLanguageRecords as $languageRecord) {
            $queryBuilderStaticLanguages = $connectionPool->getQueryBuilderForTable('static_languages');
            $staticLanguageRecord = $queryBuilderStaticLanguages
                ->select('*')
                ->from('static_languages')
                ->where(
                    $queryBuilderStaticLanguages->expr()->eq('uid', $queryBuilderStaticLanguages->createNamedParameter((int)$languageRecord['static_lang_isocode'], ParameterType::INTEGER))
                )
                ->executeQuery()
                ->fetchAssociative();

            if (!empty($staticLanguageRecord['lg_iso_2'])) {
                $connectionPool->getConnectionForTable('sys_language')->update(
                    'sys_language',
                    ['language_isocode' => strtolower($staticLanguageRecord['lg_iso_2'])],
                    ['uid' => (int)$languageRecord['uid']]
                );
            }
        }

        return true;
    }

}
