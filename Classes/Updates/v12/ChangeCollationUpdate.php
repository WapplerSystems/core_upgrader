<?php


declare(strict_types=1);


namespace TYPO3\CMS\v12\Install\Updates;

use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Schema\AbstractSchemaManager;
use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Types\StringType;
use Doctrine\DBAL\Types\TextType;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Install\Attribute\UpgradeWizard;
use TYPO3\CMS\Install\Updates\UpgradeWizardInterface;

/**
 */
#[UpgradeWizard('changeCollationUpdate')]
final class ChangeCollationUpdate implements UpgradeWizardInterface
{

    protected string $charset;
    protected string $collate;

    public function __construct()
    {

        $this->charset = $GLOBALS['TYPO3_CONF_VARS']['DB']['Connections']['Default']['tableoptions']['charset'] ?? 'utf8mb4';
        $this->collate = $GLOBALS['TYPO3_CONF_VARS']['DB']['Connections']['Default']['tableoptions']['collate'] ?? 'utf8mb4_general_ci';
    }


    /**
     * Returns the title for this update
     *
     * @return string
     */
    public function getTitle(): string
    {
        return 'Change database collation to match settings.php configuration';
    }

    /**
     * Returns the description for this update
     *
     * @return string
     */
    public function getDescription(): string
    {
        return 'This update will change the collation of all tables and fields to match the charset and collation settings in settings.php.';
    }


    public function updateNecessary(): bool
    {
        if (!isset($GLOBALS['TYPO3_CONF_VARS']['DB']['Connections']['Default']['tableoptions']['charset']) || !isset($GLOBALS['TYPO3_CONF_VARS']['DB']['Connections']['Default']['tableoptions']['collate'])) {
            return false;
        }

        $connection = GeneralUtility::makeInstance(ConnectionPool::class)->getConnectionByName('Default');
        $schemaManager = $connection->createSchemaManager();

        /** @var Table[] $tables */
        $tables = $schemaManager->listTables();

        foreach ($tables as $table) {
            $tableName = $table->getName();

            // Check table collation
            $tableCollation = $this->getTableCollation($connection, $schemaManager, $tableName);
            if ($tableCollation !== $this->collate) {
                return true;
            }

            // Check each column collation
            foreach ($table->getColumns() as $column) {

                if ($column->getType() instanceof StringType || $column->getType() instanceof TextType) {
                    $columnCollation = $this->getColumnCollation($connection, $schemaManager, $tableName, $column->getName());
                    if ($columnCollation !== $this->collate) {
                        return true;
                    }
                }
            }
        }

        return false;
    }


    /**
     * Performs the update
     *
     * @return bool
     */
    public function executeUpdate(): bool
    {
        $connection = GeneralUtility::makeInstance(ConnectionPool::class)->getConnectionByName('Default');
        $schemaManager = $connection->createSchemaManager();

        /** @var Table[] $tables */
        $tables = $schemaManager->listTables();

        foreach ($tables as $table) {
            $tableName = $table->getName();

            // Change table collation
            $sql = sprintf(
                'ALTER TABLE %s CONVERT TO CHARACTER SET %s COLLATE %s;',
                $tableName,
                $this->charset,
                $this->collate
            );
            $connection->executeStatement($sql);

            // Change each column collation
            foreach ($table->getColumns() as $column) {
                if ($column->getType() instanceof StringType || $column->getType() instanceof TextType) {

                    $columnCollation = $this->getColumnCollation($connection, $schemaManager, $tableName, $column->getName());
                    if ($columnCollation === $this->collate) {
                        continue;
                    }

                    $columnName = $column->getName();
                    $columnType = $column->getType()->getSQLDeclaration($column->toArray(), $connection->getDatabasePlatform());

                    $sql = sprintf(
                        'ALTER TABLE %s CHANGE %s %s %s COLLATE %s;',
                        $tableName,
                        $columnName,
                        $columnName,
                        strtoupper($columnType),
                        $this->collate
                    );
                    $connection->executeStatement($sql);
                }
            }
        }

        return true;
    }

    public function getPrerequisites(): array
    {
        return [
        ];
    }


    /**
     * Get the collation of a table
     *
     * @param Connection $connection
     * @param AbstractSchemaManager $schemaManager
     * @param string $tableName
     * @return string|null
     * @throws Exception
     */
    protected function getTableCollation(Connection $connection, AbstractSchemaManager $schemaManager, string $tableName): ?string
    {
        $sql = sprintf('SHOW TABLE STATUS LIKE \'%s\'', $tableName);
        $result = $connection->fetchAssociative($sql);
        return $result['Collation'] ?? null;
    }

    /**
     * Get the collation of a column
     *
     * @param Connection $connection
     * @param AbstractSchemaManager $schemaManager
     * @param string $tableName
     * @param string $columnName
     * @return string|null
     * @throws Exception
     */
    protected function getColumnCollation(Connection $connection, AbstractSchemaManager $schemaManager, string $tableName, string $columnName): ?string
    {
        $sql = sprintf('SHOW FULL COLUMNS FROM %s LIKE \'%s\'', $tableName, $columnName);
        $result = $connection->fetchAssociative($sql);
        return $result['Collation'] ?? null;
    }


    /**
     * Get the SQL type of a column
     *
     * @param Connection $connection
     * @param Column $column
     * @return string
     * @throws Exception
     */
    protected function getColumnType(Connection $connection, Column $column): string
    {
        return $column->getType()->getSQLDeclaration($column->toArray(), $connection->getDatabasePlatform());
    }
}
