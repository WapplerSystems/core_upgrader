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
use TYPO3\CMS\Install\Attribute\Operation;
use TYPO3\CMS\Install\Updates\DatabaseUpdatedPrerequisite;
use TYPO3\CMS\Install\Updates\UpgradeWizardInterface;

/**
 * Migrate the workspaces notification settings to the enhanced schema.
 */
#[Operation('workspacesNotificationSettingsUpdate')]
class WorkspacesNotificationSettingsUpdate implements UpgradeWizardInterface
{

    /**
     * @return string Unique identifier of this updater
     */
    public function getIdentifier(): string
    {
        return 'workspacesNotificationSettings';
    }

    /**
     * @return string Title of this updater
     */
    public function getTitle(): string
    {
        return 'Migrate the workspaces notification settings to the enhanced schema';
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

    /**
     * @return string Longer description of this updater
     */
    public function getDescription(): string
    {
        return 'The workspaces notification settings have been extended'
            . ' and need to be migrated to the new definitions. This update wizard'
            . ' upgrades the accordant settings in the availble workspaces and stages.';
    }

    /**
     * Checks if an update is needed
     *
     * @return bool Whether an update is needed (TRUE) or not (FALSE)
     */
    public function updateNecessary() : bool
    {
        if (!ExtensionManagementUtility::isLoaded('workspaces')) {
            return false;
        }


        $workspacesCount = $this->getDatabaseConnection()->exec_SELECTcountRows(
            'uid',
            'sys_workspace',
            'deleted=0'
        );

        $stagesCount = $this->getDatabaseConnection()->exec_SELECTcountRows(
            'uid',
            'sys_workspace_stage',
            'deleted=0'
        );

        if ($workspacesCount + $stagesCount > 0) {
            $description = 'The workspaces notification settings have been extended'
                . ' and need to be migrated to the new definitions. This update wizard'
                . ' upgrades the accordant settings in the availble workspaces and stages.';
            return true;
        }

        return false;
    }

    /**
     * Perform the database updates for workspace records
     *
     * @param array &$databaseQueries Queries done in this update
     * @param mixed &$customMessages Custom messages
     * @return bool
     */
    public function executeUpdate() : bool
    {
        $connection = $this->getConnectionPool()->getConnectionForTable('sys_workspace');

        $workspaceRecords = $databaseConnection->exec_SELECTgetRows('*', 'sys_workspace', 'deleted=0');
        foreach ($workspaceRecords as $workspaceRecord) {
            $update = $this->prepareWorkspaceUpdate($workspaceRecord);
            if ($update !== null) {
                $databaseConnection->exec_UPDATEquery('sys_workspace', 'uid=' . (int)$workspaceRecord['uid'], $update);
                $databaseQueries[] = $databaseConnection->debug_lastBuiltQuery;
            }
        }

        $stageRecords = $databaseConnection->exec_SELECTgetRows('*', 'sys_workspace_stage', 'deleted=0');
        foreach ($stageRecords as $stageRecord) {
            $update = $this->prepareStageUpdate($stageRecord);
            if ($update !== null) {
                $databaseConnection->exec_UPDATEquery('sys_workspace_stage', 'uid=' . (int)$stageRecord['uid'], $update);
                $databaseQueries[] = $databaseConnection->debug_lastBuiltQuery;
            }
        }

        return true;
    }

    /**
     * Prepares SQL updates for workspace records.
     *
     * @param array $workspaceRecord
     * @return array|NULL
     */
    protected function prepareWorkspaceUpdate(array $workspaceRecord)
    {
        if (empty($workspaceRecord['uid'])) {
            return null;
        }

        $update = [];
        $update = $this->mapSettings($workspaceRecord, $update, 'edit', 'edit');
        $update = $this->mapSettings($workspaceRecord, $update, 'publish', 'publish');
        $update = $this->mapSettings($workspaceRecord, $update, 'publish', 'execute');
        return $update;
    }

    /**
     * Prepares SQL update for stage records.
     *
     * @param array $stageRecord
     * @return array|null
     */
    protected function prepareStageUpdate(array $stageRecord)
    {
        if (empty($stageRecord['uid'])) {
            return null;
        }

        $update = [];
        $update = $this->mapSettings($stageRecord, $update);
        return $update;
    }

    /**
     * Maps settings to new meaning.
     *
     * @param array $record
     * @param array $update
     * @param string $from
     * @param string $to
     * @return array
     */
    protected function mapSettings(array $record, array $update, $from = '', $to = '')
    {
        $fromPrefix = ($from ? $from . '_' : '');
        $toPrefix = ($to ? $to . '_' : '');

        $settings = 0;
        // Previous setting: "Allow notification settings during stage change"
        if ($record[$fromPrefix . 'allow_notificaton_settings']) {
            $settings += 1;
        }
        // Previous setting: "All are selected per default (can be changed)"
        if ((int)$record[$fromPrefix . 'notification_mode'] === 0) {
            $settings += 2;
        }

        // Custom stages: preselect responsible persons (8)
        if (isset($record['responsible_persons'])) {
            $preselection = 8;
        // Workspace "edit" stage: preselect members (2)
        } elseif ($to === 'edit') {
            $preselection = 2;
        // Workspace "publish" stage: preselect owners (1)
        } elseif ($to === 'publish') {
            $preselection = 1;
        // Workspace "execute" stage: preselect owners (1) and members (2) as default
        } else {
            $preselection = 1 + 2;
        }

        $update[$toPrefix . 'allow_notificaton_settings'] = $settings;
        $update[$toPrefix . 'notification_preselection'] = $preselection;

        return $update;
    }

    protected function getConnectionPool(): ConnectionPool
    {
        return GeneralUtility::makeInstance(ConnectionPool::class);
    }
}
