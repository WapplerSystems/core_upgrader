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

use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Upgrades\DatabaseUpdatedPrerequisite;
use TYPO3\CMS\Core\Upgrades\UpgradeWizardInterface;
use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Attribute\UpgradeWizard;

/**
 * Migrate the workspaces notification settings to the enhanced schema.
 */
#[UpgradeWizard('workspacesNotificationSettingsUpdate')]
class WorkspacesNotificationSettingsUpdate implements UpgradeWizardInterface
{

    public function getTitle(): string
    {
        return 'Migrate the workspaces notification settings to the enhanced schema';
    }

    public function getPrerequisites(): array
    {
        return [
            DatabaseUpdatedPrerequisite::class
        ];
    }

    public function getDescription(): string
    {
        return 'The workspaces notification settings have been extended'
            . ' and need to be migrated to the new definitions. This update wizard'
            . ' upgrades the accordant settings in the available workspaces and stages.';
    }

    public function updateNecessary(): bool
    {
        if (!ExtensionManagementUtility::isLoaded('workspaces')) {
            return false;
        }
        // The legacy `notification_mode` columns were dropped in TYPO3 8.
        // On any newer installation the migration is implicitly done — bail out.
        if (!$this->hasLegacyColumn('sys_workspace', 'edit_notification_mode')
            && !$this->hasLegacyColumn('sys_workspace_stage', 'notification_mode')
        ) {
            return false;
        }

        $workspacesCount = (int)$this->getConnectionPool()
            ->getQueryBuilderForTable('sys_workspace')
            ->count('uid')
            ->from('sys_workspace')
            ->where('deleted = 0')
            ->executeQuery()
            ->fetchOne();

        $stagesCount = (int)$this->getConnectionPool()
            ->getQueryBuilderForTable('sys_workspace_stage')
            ->count('uid')
            ->from('sys_workspace_stage')
            ->where('deleted = 0')
            ->executeQuery()
            ->fetchOne();

        return ($workspacesCount + $stagesCount) > 0;
    }

    public function executeUpdate(): bool
    {
        if (!$this->updateNecessary()) {
            return true;
        }

        $workspaceConnection = $this->getConnectionPool()->getConnectionForTable('sys_workspace');
        $workspaceRecords = $workspaceConnection->select(['*'], 'sys_workspace', ['deleted' => 0])->fetchAllAssociative();
        foreach ($workspaceRecords as $workspaceRecord) {
            $update = $this->prepareWorkspaceUpdate($workspaceRecord);
            if ($update !== null && $update !== []) {
                $workspaceConnection->update('sys_workspace', $update, ['uid' => (int)$workspaceRecord['uid']]);
            }
        }

        $stageConnection = $this->getConnectionPool()->getConnectionForTable('sys_workspace_stage');
        $stageRecords = $stageConnection->select(['*'], 'sys_workspace_stage', ['deleted' => 0])->fetchAllAssociative();
        foreach ($stageRecords as $stageRecord) {
            $update = $this->prepareStageUpdate($stageRecord);
            if ($update !== null && $update !== []) {
                $stageConnection->update('sys_workspace_stage', $update, ['uid' => (int)$stageRecord['uid']]);
            }
        }

        return true;
    }

    protected function prepareWorkspaceUpdate(array $workspaceRecord): ?array
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

    protected function prepareStageUpdate(array $stageRecord): ?array
    {
        if (empty($stageRecord['uid'])) {
            return null;
        }

        return $this->mapSettings($stageRecord, [], '', '');
    }

    protected function mapSettings(array $record, array $update, string $from = '', string $to = ''): array
    {
        $fromPrefix = ($from ? $from . '_' : '');
        $toPrefix = ($to ? $to . '_' : '');

        $settings = 0;
        if (!empty($record[$fromPrefix . 'allow_notificaton_settings'])) {
            ++$settings;
        }
        if ((int)($record[$fromPrefix . 'notification_mode'] ?? 0) === 0) {
            $settings += 2;
        }

        if (isset($record['responsible_persons'])) {
            $preselection = 8;
        } elseif ($to === 'edit') {
            $preselection = 2;
        } elseif ($to === 'publish') {
            $preselection = 1;
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

    private function hasLegacyColumn(string $table, string $column): bool
    {
        try {
            $schemaManager = $this->getConnectionPool()
                ->getConnectionForTable($table)
                ->createSchemaManager();
            $columns = $schemaManager->listTableColumns($table);
            return isset($columns[strtolower($column)]);
        } catch (\Throwable) {
            return false;
        }
    }
}