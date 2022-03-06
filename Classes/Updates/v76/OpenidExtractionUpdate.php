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

use TYPO3\CMS\Install\Updates\AbstractDownloadExtensionUpdate;
use TYPO3\CMS\Install\Updates\Confirmation;
use TYPO3\CMS\Install\Updates\DatabaseUpdatedPrerequisite;
use TYPO3\CMS\Install\Updates\ExtensionModel;

/**
 * Installs and downloads EXT:openid if needed
 */
class OpenidExtractionUpdate extends AbstractDownloadExtensionUpdate
{

    /**
     * @return string Unique identifier of this updater
     */
    public function getIdentifier(): string
    {
        return 'OpenidExtraction';
    }



    public function __construct()
    {
        $this->extension = new ExtensionModel(
            'openid',
            'OpenID authentication',
            '7.6.4',
            'friendsoftypo3/openid',
            'Adds OpenID authentication to TYPO3',
        );

        $this->confirmation = new Confirmation(
            'Are you sure?',
            '. ' . $this->extension->getDescription(),
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
     * Return the speaking name of this wizard
     *
     * @return string
     */
    public function getTitle(): string
    {
        return 'Installs extension "openid" from TER if openid is used.';
    }

    /**
     * Return the description for this wizard
     *
     * @return string
     */
    public function getDescription(): string
    {
        return 'The extension "openid" (OpenID authentication) was extracted into '
            . 'the TYPO3 Extension Repository. This update checks if openid id used and '
            . 'downloads the TYPO3 Extension from the TER.';
    }

    /**
     * @var string
     */
    protected $extensionKey = 'openid';

    /**
     * @var array
     */
    protected $extensionDetails = [
        'openid' => [
            'title' => 'OpenID authentication',
            'description' => 'Adds OpenID authentication to TYPO3',
            'versionString' => '7.6.4',
        ]
    ];

    /**
     * Is an update necessary?
     * Is used to determine whether a wizard needs to be run.
     *
     * @return bool
     */
    public function updateNecessary(): bool
    {
        $updateNeeded = false;

        $columnsExists = false;

        $columns = $this->getDatabaseConnection()->admin_get_fields('fe_users');
        if (isset($columns['tx_openid_openid'])) {
            $columnsExists = true;
        }
        $columns = $this->getDatabaseConnection()->admin_get_fields('be_users');
        if (isset($columns['tx_openid_openid'])) {
            $columnsExists = true;
        }
        if ($columnsExists) {
            $updateNeeded = true;
        }

        return $updateNeeded;
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
        return [
            DatabaseUpdatedPrerequisite::class
        ];
    }
}
