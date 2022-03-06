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
use TYPO3\CMS\Install\Updates\AbstractDownloadExtensionUpdate;
use TYPO3\CMS\Install\Updates\Confirmation;
use TYPO3\CMS\Install\Updates\ExtensionModel;

/**
 * Installs and downloads EXT:compatibility6 if needed
 */
class Compatibility6ExtractionUpdate extends AbstractDownloadExtensionUpdate
{
    /**
     * @var string
     */
    protected $title = 'Installs extension "compatibility6" from TER';

    /**
     * @var string
     */
    protected $extensionKey = 'compatibility6';

    /**
     * @var array
     */
    protected $extensionDetails = [
        'compatibility6' => [
            'title' => 'Compatibility Mode for TYPO3 CMS 6.x',
            'description' => 'Provides an additional backwards-compatibility layer with legacy functionality for sites that haven\'t fully migrated to TYPO3 CMS 7 yet.',
            'versionString' => '7.6.4',
        ]
    ];


    /**
     * @var \TYPO3\CMS\Install\Updates\ExtensionModel
     */
    protected $extension;

    /**
     * @var \TYPO3\CMS\Install\Updates\Confirmation
     */
    protected $confirmation;

    public function __construct()
    {
        $this->extension = new ExtensionModel(
            'compatibility6',
            'Compatibility Mode for TYPO3 v6',
            '7.6.4',
            'friendsoftypo3/compatibility6',
            'Provides an additional backwards-compatibility layer with legacy functionality for sites that haven\'t fully migrated to TYPO3 CMS 7 yet.',
        );

        $this->confirmation = new Confirmation(
            'Are you sure?',
            'The compatibility extensions come with a performance penalty, use only if needed. ' . $this->extension->getDescription(),
            false
        );
    }


    /**
     * Return a confirmation message instance
     *
     * @return \TYPO3\CMS\Install\Updates\Confirmation
     */
    public function getConfirmation(): Confirmation
    {
        return $this->confirmation;
    }

    /**
     * Return the identifier for this wizard
     * This should be the same string as used in the ext_localconf class registration
     *
     * @return string
     */
    public function getIdentifier(): string
    {
        return 'compatibility6Extension';
    }

    /**
     * Return the speaking name of this wizard
     *
     * @return string
     */
    public function getTitle(): string
    {
        return 'Install compatibility extension for TYPO3 6 compatibility';
    }

    /**
     * Return the description for this wizard
     *
     * @return string
     */
    public function getDescription(): string
    {
        return 'The extension "compatibility6" (Compatibility Mode for TYPO3 v6) was extracted into '
            . 'the TYPO3 Extension Repository. This update downloads the TYPO3 Extension from the TER.';
    }

    /**
     * Is an update necessary?
     * Is used to determine whether a wizard needs to be run.
     *
     * @return bool
     */
    public function updateNecessary(): bool
    {
        return !ExtensionManagementUtility::isLoaded('compatibility6');
    }

    /**
     * Returns an array of class names of Prerequisite classes
     * This way a wizard can define dependencies like "database up-to-date" or
     * "reference index updated"
     *
     * @return string[]
     */
    public function getPrerequisites(): array
    {
        return [];
    }

}
