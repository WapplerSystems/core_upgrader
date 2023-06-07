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
use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;
use TYPO3\CMS\Install\Attribute\UpgradeWizard;
use TYPO3\CMS\Install\Updates\DatabaseUpdatedPrerequisite;
use TYPO3\CMS\Install\Updates\UpgradeWizardInterface;

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

        $emptyValue = $this->getDatabaseConnection()->fullQuoteStr('', 'sys_language');
        $migratableLanguageRecords = $this->getDatabaseConnection()->exec_SELECTcountRows('uid', 'sys_language', 'language_isocode=' . $emptyValue . ' AND CAST(static_lang_isocode AS CHAR) != ' . $emptyValue);
        if ($migratableLanguageRecords === 0) {
            return false;
        }

        return true;
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
     */
    public function executeUpdate(): bool
    {
        $emptyValue =  $this->getDatabaseConnection()->fullQuoteStr('', 'sys_language');
        $migrateableLanguageRecords = $this->getDatabaseConnection()->exec_SELECTgetRows('uid,static_lang_isocode', 'sys_language', 'language_isocode=' . $emptyValue . ' AND CAST(static_lang_isocode AS CHAR) != ' . $emptyValue);
        if (!empty($migrateableLanguageRecords)) {
            foreach ($migrateableLanguageRecords as $languageRecord) {
                $staticLanguageRecord = $this->getDatabaseConnection()->exec_SELECTgetSingleRow('*', 'static_languages', 'uid=' . (int)$languageRecord['static_lang_isocode']);
                if (!empty($staticLanguageRecord['lg_iso_2'])) {
                    $this->getDatabaseConnection()->exec_UPDATEquery(
                        'sys_language',
                        'uid=' . (int)$languageRecord['uid'],
                        [
                            'language_isocode' => strtolower($staticLanguageRecord['lg_iso_2'])
                        ]
                    );
                }
            }
        }

        return true;
    }
}
