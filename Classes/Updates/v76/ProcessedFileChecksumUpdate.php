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
use TYPO3\CMS\Core\Resource\ProcessedFile;
use TYPO3\CMS\Core\Resource\ResourceFactory;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Install\Updates\DatabaseUpdatedPrerequisite;
use TYPO3\CMS\Install\Updates\UpgradeWizardInterface;

/**
 * Updates the checksum of sys_file_processedfile records to avoid regeneration of the thumbnails
 */
class ProcessedFileChecksumUpdate implements UpgradeWizardInterface
{

    /**
     * @return string Unique identifier of this updater
     */
    public function getIdentifier(): string
    {
        return 'processedFileChecksum';
    }

    /**
     * @return string Title of this updater
     */
    public function getTitle(): string
    {
        return '[Optional] Update sys_file_processedfile records to match new checksum calculation.';
    }

    /**
     * @return string Longer description of this updater
     */
    public function getDescription(): string
    {
        return 'The checksum calculation for processed files (image thumbnails) has been changed with TYPO3 CMS 7.3 and 6.2.13.
This means that your processed files need to be updated, if you update from versions <strong>below TYPO3 CMS 7.3 or 6.2.13</strong>.<br />
This can either happen on demand, when the processed file is first needed, or by executing this wizard, which updates all processed images at once.<br />
<strong>Important:</strong> If you have lots of processed files, you should prefer using this wizard, otherwise this might cause a lot of work for your server.';
    }

    /**
     * Checks if an update is needed
     *
     * @return bool Whether an update is needed (TRUE) or not (FALSE)
     */
    public function updateNecessary(): bool
    {

        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable('sys_file_processedfile');
        return (bool)$queryBuilder->count('*')
            ->from('sys_file_processedfile')
            ->leftJoin('sys_file_processedfile','sys_registry','sys_registry', 'CAST(entry_key AS CHAR) = CAST(sys_file_processedfile.uid AS CHAR) AND entry_namespace = \'ProcessedFileChecksumUpdate\'')
            ->where(
                $queryBuilder->expr()->or(
                    $queryBuilder->expr()->and(
                        $queryBuilder->expr()->isNull('entry_key', $queryBuilder->createNamedParameter('_FILE', \PDO::PARAM_STR)),
                        $queryBuilder->expr()->neq('sys_file_processedfile.identifier', $queryBuilder->createNamedParameter('', \PDO::PARAM_STR))
                    ),
                ),
                $queryBuilder->expr()->isNull('sys_file_processedfile.width')
            )
            ->execute()
            ->fetchColumn(0);
/*
        $join = 'sys_file_processedfile LEFT JOIN sys_registry ON CAST(entry_key AS CHAR) = CAST(sys_file_processedfile.uid AS CHAR) AND entry_namespace = \'ProcessedFileChecksumUpdate\'';
        $count = $this->getDatabaseConnection()->exec_SELECTcountRows('*', $join, '(entry_key IS NULL AND sys_file_processedfile.identifier <> \'\') OR sys_file_processedfile.width IS NULL');
        if (!$count) {
            return false;
        }
        return true;
*/
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
     * Performs the update
     *
     * @return bool
     */
    public function executeUpdate(): bool
    {

        // remove all invalid records which hold NULL values
        $db->exec_DELETEquery('sys_file_processedfile', 'width IS NULL or height IS NULL');

        $factory = GeneralUtility::makeInstance(ResourceFactory::class);

        $join = 'sys_file_processedfile LEFT JOIN sys_registry ON entry_key = CAST(sys_file_processedfile.uid AS CHAR) AND entry_namespace = \'ProcessedFileChecksumUpdate\'';
        $res = $db->exec_SELECTquery('sys_file_processedfile.*', $join, 'entry_key IS NULL AND sys_file_processedfile.identifier <> \'\'');
        while ($processedFileRow = $db->sql_fetch_assoc($res)) {
            try {
                $storage = $factory->getStorageObject($processedFileRow['storage']);
            } catch (\InvalidArgumentException $e) {
                $storage = null;
            }
            if (!$storage) {
                // invalid storage, delete record, we can't take care of the associated file
                $db->exec_DELETEquery('sys_file_processedfile', 'uid=' . $processedFileRow['uid']);
                continue;
            }

            if ($storage->getDriverType() !== 'Local') {
                // non-local storage, we can't treat this, skip the record and mark it done
                $db->exec_INSERTquery('sys_registry', ['entry_namespace' => 'ProcessedFileChecksumUpdate', 'entry_key' => $processedFileRow['uid']]);
                continue;
            }

            $configuration = $storage->getConfiguration();
            if ($configuration['pathType'] === 'relative') {
                $absoluteBasePath = PATH_site . $configuration['basePath'];
            } else {
                $absoluteBasePath = $configuration['basePath'];
            }
            $filePath = rtrim($absoluteBasePath, '/') . '/' . ltrim($processedFileRow['identifier'], '/');

            try {
                $originalFile = $factory->getFileObject($processedFileRow['original']);
            } catch (\Exception $e) {
                // no original file there anymore, delete local file
                @unlink($filePath);
                $db->exec_DELETEquery('sys_file_processedfile', 'uid=' . $processedFileRow['uid']);
                continue;
            }

            $processedFileObject = new ProcessedFile($originalFile, '', [], $processedFileRow);

            // calculate new checksum and name
            $newChecksum = $processedFileObject->calculateChecksum();

            // if the checksum already matches, there is nothing to do
            if ($newChecksum !== $processedFileRow['checksum']) {
                $newName = str_replace($processedFileRow['checksum'], $newChecksum, $processedFileRow['name']);
                $newIdentifier = str_replace($processedFileRow['checksum'], $newChecksum, $processedFileRow['identifier']);
                $newFilePath = str_replace($processedFileRow['checksum'], $newChecksum, $filePath);

                // rename file
                if (@rename($filePath, $newFilePath)) {
                    // save result back into database
                    $fields = [
                        'tstamp' => time(),
                        'identifier' => $newIdentifier,
                        'name' => $newName,
                        'checksum' => $newChecksum
                    ];
                    $db->exec_UPDATEquery('sys_file_processedfile', 'uid=' . $processedFileRow['uid'], $fields);
                }
                // if the rename of the file failed, keep the record, but do not bother with it again
            }

            // remember we finished this record
            $db->exec_INSERTquery('sys_registry', ['entry_namespace' => 'ProcessedFileChecksumUpdate', 'entry_key' => $processedFileRow['uid']]);
        }

        $db->exec_DELETEquery('sys_registry', 'entry_namespace = \'ProcessedFileChecksumUpdate\'');
        return true;
    }
}
