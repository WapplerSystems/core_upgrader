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

use TYPO3\CMS\Core\Configuration\ConfigurationManager;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Install\Attribute\Operation;
use TYPO3\CMS\Install\Updates\DatabaseUpdatedPrerequisite;
use TYPO3\CMS\Install\Updates\UpgradeWizardInterface;

/**
 * Move access right parameters from "BE" to "SYS" configuration section
 */
#[Operation('accessRightParameters')]
class AccessRightParametersUpdate implements UpgradeWizardInterface
{

    /**
     * @return string Unique identifier of this updater
     */
    public function getIdentifier(): string
    {
        return 'accessRightParameters';
    }

    /**
     * @return string Title of this updater
     */
    public function getTitle(): string
    {
        return 'Move access right parameters configuration to "SYS" section';
    }

    /**
     * @return string Longer description of this updater
     */
    public function getDescription(): string
    {
        return 'Some access right parameters were moved from the "BE" to the "SYS" configuration section. ' .
            'The update wizards moves the settings to the new configuration destination.';
    }

    /**
     * @var array
     */
    protected $movedAccessRightConfigurationSettings = [
        'BE/fileCreateMask' => 'SYS/fileCreateMask',
        'BE/folderCreateMask' => 'SYS/folderCreateMask',
        'BE/createGroup' => 'SYS/createGroup',
    ];



    /**
     * Checks if an update is needed
     *
     * @return bool Whether an update is needed (TRUE) or not (FALSE)
     */
    public function updateNecessary() : bool
    {
        $updateNeeded = false;

        /** @var ConfigurationManager $configurationManager */
        $configurationManager = GeneralUtility::makeInstance(ConfigurationManager::class);

        // If the local configuration path can be accessed, the path is valid and the update wizard has to be executed
        foreach ($this->movedAccessRightConfigurationSettings as $oldPath => $newPath) {
            try {
                $configurationManager->getLocalConfigurationValueByPath($oldPath);
                $updateNeeded = true;
                break;
            } catch (\RuntimeException $e) {
            }
        }

        return $updateNeeded;
    }

    /**
     * Performs the configuration update
     *
     * @return bool
     */
    public function executeUpdate(): bool
    {
        /** @var ConfigurationManager $configurationManager */
        $configurationManager = GeneralUtility::makeInstance(ConfigurationManager::class);
        foreach ($this->movedAccessRightConfigurationSettings as $oldPath => $newPath) {
            try {
                $value = $configurationManager->getLocalConfigurationValueByPath($oldPath);
                $configurationManager->setLocalConfigurationValueByPath($newPath, $value);
            } catch (\RuntimeException $e) {
            }
        }
        $configurationManager->removeLocalConfigurationKeysByPath(array_keys($this->movedAccessRightConfigurationSettings));

        return true;
    }

    /**
     * @return string[] All new fields and tables must exist
     */
    public function getPrerequisites(): array
    {
        return [
        ];
    }
}
