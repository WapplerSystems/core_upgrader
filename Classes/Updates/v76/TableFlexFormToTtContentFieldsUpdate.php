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
use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Install\Updates\DatabaseUpdatedPrerequisite;
use TYPO3\CMS\Install\Updates\UpgradeWizardInterface;

/**
 * Migrate the Flexform for CType 'table' to regular fields in tt_content
 */
class TableFlexFormToTtContentFieldsUpdate implements UpgradeWizardInterface
{

    /**
     * @return string Unique identifier of this updater
     */
    public function getIdentifier(): string
    {
        return 'tableFlexFormToTtContentFields';
    }

    /**
     * @return string Title of this updater
     */
    public function getTitle(): string
    {
        return 'Migrate the Flexform for CType "table" to regular fields in tt_content';
    }

    /**
     * @return string Longer description of this updater
     */
    public function getDescription(): string
    {
        return 'The extension "frontend" uses regular database fields in the tt_content table ' .
            'for the CType "table". Before this was a FlexForm.<br /><br />' .
            'This update wizard migrates these FlexForms to regular database fields.';
    }


    /**
     * Checks if an update is needed
     *
     * @return bool Whether an update is needed (TRUE) or not (FALSE)
     */
    public function updateNecessary(): bool
    {
        if (!ExtensionManagementUtility::isLoaded('css_styled_content')) {
            return false;
        }

        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable('tt_content');
        return (bool)$queryBuilder->count('uid')
            ->from('tt_content')
            ->where(
                $queryBuilder->expr()->eq('CType', $queryBuilder->createNamedParameter('table', \PDO::PARAM_STR)),
                $queryBuilder->expr()->isNotNull('pi_flexform'),
                $queryBuilder->expr()->eq('deleted', $queryBuilder->createNamedParameter(0, \PDO::PARAM_INT))
            )
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
     * Performs the database update if CType 'table' still has content in pi_flexform
     *
     * @return bool
     */
    public function executeUpdate(): bool
    {

        $databaseResult = $databaseConnection->exec_SELECTquery(
            'uid, pi_flexform',
            'tt_content',
            'CType=\'table\' AND pi_flexform IS NOT NULL AND deleted = 0'
        );

        while ($tableRecord = $databaseConnection->sql_fetch_assoc($databaseResult)) {
            $flexForm = $this->initializeFlexForm($tableRecord['pi_flexform']);

            if (is_array($flexForm)) {
                $fields = $this->mapFieldsFromFlexForm($flexForm);

                // Set pi_flexform to NULL
                $fields['pi_flexform'] = null;

                $databaseConnection->exec_UPDATEquery(
                    'tt_content',
                    'uid=' . (int)$tableRecord['uid'],
                    $fields
                );

                $databaseQueries[] = $databaseConnection->debug_lastBuiltQuery;
            }
        }


        return true;
    }

    /**
     * Map the old FlexForm values to the new database fields
     * and fill them with the proper data
     *
     * @param array $flexForm The content of the FlexForm
     * @return array The fields which need to be updated in the tt_content table
     */
    protected function mapFieldsFromFlexForm($flexForm)
    {
        $fields = [];

        $mapping = [
            'table_caption' => [
                'sheet' => 'sDEF',
                'fieldName' => 'acctables_caption',
                'default' => '',
                'values' => 'passthrough'
            ],
            'table_delimiter' => [
                'sheet' => 's_parsing',
                'fieldName' => 'tableparsing_delimiter',
                'default' => 124,
                'values' => 'passthrough'
            ],
            'table_enclosure' => [
                'sheet' => 's_parsing',
                'fieldName' => 'tableparsing_quote',
                'default' => 0,
                'values' => 'passthrough'
            ],
            'table_header_position' => [
                'sheet' => 'sDEF',
                'fieldName' => 'acctables_headerpos',
                'default' => 0,
                'values' => [
                    'top' => 1,
                    'left' => 2
                ]
            ],
            'table_tfoot' => [
                'sheet' => 'sDEF',
                'fieldName' => 'acctables_tfoot',
                'default' => 0,
                'values' => 'passthrough'
            ]
        ];

        foreach ($mapping as $fieldName => $configuration) {
            $flexFormValue = $this->getFlexFormValue($flexForm, $configuration['fieldName'], $configuration['sheet']);

            if ($flexFormValue !== '') {
                if ($configuration['values'] === 'passthrough') {
                    $fields[$fieldName] = $flexFormValue;
                } elseif (is_array($configuration['values'])) {
                    $fields[$fieldName] = $configuration['values'][$flexFormValue];
                }
            } else {
                $fields[$fieldName] = $configuration['default'];
            }
        }

        return $fields;
    }

    /**
     * Convert the XML of the FlexForm to an array
     *
     * @param string|NULL $flexFormXml The XML of the FlexForm
     * @return array|NULL Converted XML to array
     */
    protected function initializeFlexForm($flexFormXml)
    {
        $flexForm = null;

        if ($flexFormXml) {
            $flexForm = GeneralUtility::xml2array($flexFormXml);
            if (!is_array($flexForm)) {
                $flexForm = null;
            }
        }

        return $flexForm;
    }

    /**
     * @param array $flexForm The content of the FlexForm
     * @param string $fieldName The field name to get the value for
     * @param string $sheet The sheet on which this value is located
     * @return string The value
     */
    protected function getFlexFormValue(array $flexForm, $fieldName, $sheet = 'sDEF')
    {
        return $flexForm['data'][$sheet]['lDEF'][$fieldName]['vDEF'];
    }
}
