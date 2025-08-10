<?php namespace Swift\Migration\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use Config\Database;

class GenerateMigration extends BaseCommand
{
    protected $group       = 'Database';
    protected $name        = 'db:generate-migration';
    protected $description = 'Generate migration files from current database schema with semantic versioning';

    protected $usage = 'db:generate-migration [options]';
    protected $options = [
        '--table'       => 'Generate migration for specific table only',
        '--update'      => 'Update current migration version with detected changes (overwrites existing)',
        '--version'     => 'Specify version (e.g., 1.2.0). If not provided, auto-increment will be used',
        '--major'       => 'Increment major version (breaking changes)',
        '--minor'       => 'Increment minor version (new features)',
        '--patch'       => 'Increment patch version (bug fixes)',
        '--reset'       => 'Delete all previous migrations and start fresh with specified version',
        '--force'       => 'Force overwrite existing migration files',
        '--description' => 'Add custom description for this migration version',
    ];

    protected $db;
    protected $migrationPath;
    protected $versionFile;

    public function run(array $params)
    {
        $this->db = Database::connect();
        $this->migrationPath = APPPATH . 'Database/Migrations/';
        $this->versionFile = APPPATH . 'Database/migration_version.json';

        // Ensure migrations directory exists
        if (!is_dir($this->migrationPath)) {
            mkdir($this->migrationPath, 0755, true);
        }

        $table = CLI::getOption('table');
        $isUpdate = CLI::getOption('update');
        $force = CLI::getOption('force');
        $regenerate = CLI::getOption('regenerate');
        $customVersion = CLI::getOption('version');
        $customDescription = CLI::getOption('description');

        // Handle version increment options
        $major = CLI::getOption('major');
        $minor = CLI::getOption('minor');
        $patch = CLI::getOption('patch');

        if ($regenerate) {
            $this->regenerateCurrentVersion($table, $customDescription);
        } elseif ($isUpdate) {
            $this->generateUpdateMigration($table, $force, $customVersion, $major, $minor, $patch, $customDescription);
        } else {
            $this->generateInitialMigration($table, $force, $customVersion, $customDescription);
        }
    }

    protected function getCurrentVersion()
    {
        if (!file_exists($this->versionFile)) {
            return '0.0.0';
        }

        $versionData = json_decode(file_get_contents($this->versionFile), true);
        return $versionData['current_version'] ?? '0.0.0';
    }

    protected function getNextVersion($customVersion = null, $major = false, $minor = false, $patch = false)
    {
        if ($customVersion) {
            if (!$this->isValidVersion($customVersion)) {
                CLI::error("Invalid version format. Use semantic versioning (e.g., 1.2.3)");
                exit(1);
            }
            return $customVersion;
        }

        $currentVersion = $this->getCurrentVersion();
        list($majorVer, $minorVer, $patchVer) = explode('.', $currentVersion);

        if ($major) {
            return ($majorVer + 1) . '.0.0';
        } elseif ($minor) {
            return $majorVer . '.' . ($minorVer + 1) . '.0';
        } elseif ($patch) {
            return $majorVer . '.' . $minorVer . '.' . ($patchVer + 1);
        } else {
            // Default to minor increment
            return $majorVer . '.' . ($minorVer + 1) . '.0';
        }
    }

    protected function isValidVersion($version)
    {
        return preg_match('/^\d+\.\d+\.\d+$/', $version);
    }

    protected function updateVersionFile($version, $description = '')
    {
        $versionData = [
            'current_version' => $version,
            'last_updated' => date('Y-m-d H:i:s'),
            'description' => $description,
            'history' => []
        ];

        if (file_exists($this->versionFile)) {
            $existing = json_decode(file_get_contents($this->versionFile), true);
            if (isset($existing['history'])) {
                $versionData['history'] = $existing['history'];
            }
            // Add current version to history
            if (isset($existing['current_version']) && $existing['current_version'] !== $version) {
                $versionData['history'][] = [
                    'version' => $existing['current_version'],
                    'date' => $existing['last_updated'] ?? date('Y-m-d H:i:s'),
                    'description' => $existing['description'] ?? ''
                ];
            }
        }

        file_put_contents($this->versionFile, json_encode($versionData, JSON_PRETTY_PRINT));
    }

    protected function generateInitialMigration($specificTable = null, $force = false, $customVersion = null, $customDescription = null)
    {
        $version = $customVersion ?: '1.0.0';
        $description = $customDescription ?: 'Initial database schema migration';

        CLI::write("Generating initial migration v{$version} from database schema...", 'green');
        if ($customDescription) {
            CLI::write("Description: {$description}", 'cyan');
        }

        $tables = $specificTable ? [$specificTable] : $this->getTables();
        $migrationFiles = [];

        foreach ($tables as $table) {
            $fileName = $this->generateTableMigration($table, 'create', $version, $force);
            if ($fileName) {
                $migrationFiles[] = $fileName;
            }
        }

        if (!empty($migrationFiles)) {
            $this->updateVersionFile($version, $description);
            CLI::write("Migration v{$version} generation completed!", 'green');
            CLI::write("Files created: " . implode(', ', $migrationFiles), 'cyan');
        }
    }

    protected function updateCurrentVersion($specificTable = null, $customDescription = null)
    {
        $currentVersion = $this->getCurrentVersion();

        if ($currentVersion === '0.0.0') {
            CLI::error('No current version found. Generate an initial migration first.');
            return;
        }

        $description = $customDescription ?: "Updated schema for v{$currentVersion}";

        CLI::write("Updating current version v{$currentVersion} with detected changes...", 'green');
        if ($customDescription) {
            CLI::write("Description: {$description}", 'cyan');
        }

        // Remove existing migration files for current version
        $this->removeVersionFiles($currentVersion);

        $tables = $specificTable ? [$specificTable] : $this->getTables();
        $hasChanges = false;
        $migrationFiles = [];

        foreach ($tables as $table) {
            if ($this->hasSchemaChanges($table)) {
                $fileName = $this->generateTableMigration($table, 'update', $currentVersion, true);
                if ($fileName) {
                    $migrationFiles[] = $fileName;
                    $hasChanges = true;

                    // Log what changes were detected
                    $changes = $this->getSchemaChanges($table);
                    $this->logSchemaChanges($table, $changes);
                }
            } else {
                CLI::write("No changes detected for table: {$table}", 'yellow');
            }
        }

        if (!$hasChanges) {
            CLI::write('No schema changes detected.', 'yellow');
        } else {
            $this->updateVersionFile($currentVersion, $description);
            CLI::write("Current version v{$currentVersion} updated successfully!", 'green');
            CLI::write("Files created: " . implode(', ', $migrationFiles), 'cyan');
        }
    }

    protected function generateNewVersion($specificTable = null, $force = false, $customVersion = null, $major = false, $minor = false, $patch = false, $customDescription = null)
    {
        $version = $this->getNextVersion($customVersion, $major, $minor, $patch);

        // Generate description based on version increment type or use custom
        if ($customDescription) {
            $description = $customDescription;
        } else {
            $changeType = $major ? 'major' : ($minor ? 'minor' : 'patch');
            $description = "Schema update ({$changeType} version)";
        }

        CLI::write("Generating new migration v{$version} by comparing database schema...", 'green');
        if ($customDescription) {
            CLI::write("Description: {$description}", 'cyan');
        }

        $tables = $specificTable ? [$specificTable] : $this->getTables();
        $hasChanges = false;
        $migrationFiles = [];

        foreach ($tables as $table) {
            if ($this->hasSchemaChanges($table)) {
                $fileName = $this->generateTableMigration($table, 'update', $version, $force);
                if ($fileName) {
                    $migrationFiles[] = $fileName;
                    $hasChanges = true;

                    // Log what changes were detected
                    $changes = $this->getSchemaChanges($table);
                    $this->logSchemaChanges($table, $changes);
                }
            } else {
                CLI::write("No changes detected for table: {$table}", 'yellow');
            }
        }

        if (!$hasChanges) {
            CLI::write('No schema changes detected.', 'yellow');
        } else {
            $this->updateVersionFile($version, $description);
            CLI::write("New migration v{$version} generation completed!", 'green');
            CLI::write("Files created: " . implode(', ', $migrationFiles), 'cyan');
        }
    }

    protected function resetAllMigrations($customVersion = null, $customDescription = null)
    {
        $version = $customVersion ?: '1.0.0';
        $description = $customDescription ?: "Fresh migration reset to v{$version}";

        CLI::write("Resetting all migrations and starting fresh with v{$version}...", 'green');
        if ($customDescription) {
            CLI::write("Description: {$description}", 'cyan');
        }

        // Confirm the destructive action
        if (!CLI::prompt('This will delete ALL existing migration files. Are you sure?', ['y', 'n']) === 'y') {
            CLI::write('Operation cancelled.', 'yellow');
            return;
        }

        // Remove all existing migration files
        $this->removeAllMigrationFiles();

        // Generate fresh migrations from current database state
        $tables = $this->getTables();
        $migrationFiles = [];

        foreach ($tables as $table) {
            $fileName = $this->generateTableMigration($table, 'create', $version, true);
            if ($fileName) {
                $migrationFiles[] = $fileName;
            }
        }

        if (!empty($migrationFiles)) {
            // Reset version file completely
            $this->resetVersionFile($version, $description);
            CLI::write("Migration reset completed! Fresh start with v{$version}", 'green');
            CLI::write("Files created: " . implode(', ', $migrationFiles), 'cyan');
        }
    }

    protected function removeAllMigrationFiles()
    {
        $files = glob($this->migrationPath . "v*.php");
        foreach ($files as $file) {
            unlink($file);
            CLI::write("Removed: " . basename($file), 'red');
        }

        if (empty($files)) {
            CLI::write("No existing migration files found.", 'yellow');
        }
    }

    protected function resetVersionFile($version, $description)
    {
        $versionData = [
            'current_version' => $version,
            'last_updated' => date('Y-m-d H:i:s'),
            'description' => $description,
            'history' => []
        ];

        file_put_contents($this->versionFile, json_encode($versionData, JSON_PRETTY_PRINT));
    }

    protected function removeVersionFiles($version)
    {
        $files = glob($this->migrationPath . "v{$version}_*.php");
        foreach ($files as $file) {
            unlink($file);
            CLI::write("Removed: " . basename($file), 'yellow');
        }
    }

    protected function generateTableMigration($tableName, $type = 'create', $version = '1.0.0', $force = false)
    {
        $className = $this->getClassName($tableName, $type);
        $fileName = "v{$version}_{$tableName}_{$type}.php";
        $filePath = $this->migrationPath . $fileName;

        if (file_exists($filePath) && !$force) {
            CLI::write("Migration file already exists: {$fileName}. Use --force to overwrite.", 'yellow');
            return null;
        }

        $migrationContent = $this->generateMigrationContent($tableName, $className, $type, $version);

        file_put_contents($filePath, $migrationContent);
        CLI::write("Created migration: {$fileName}", 'green');

        return $fileName;
    }

    protected function generateMigrationContent($tableName, $className, $type, $version)
    {
        $fields = $this->getTableFields($tableName);
        $indexes = $this->getTableIndexes($tableName);
        $foreignKeys = $this->getForeignKeys($tableName);

        $upMethod = $type === 'create' ?
            $this->generateCreateTableMethod($tableName, $fields, $indexes, $foreignKeys) :
            $this->generateUpdateTableMethod($tableName, $fields, $indexes, $foreignKeys);

        $downMethod = $type === 'create' ?
            $this->generateDropTableMethod($tableName) :
            $this->generateRevertUpdateMethod($tableName);

        return <<<PHP
<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Migration for {$tableName} table
 * Version: {$version}
 * Generated: {date('Y-m-d H:i:s')}
 */
class {$className} extends Migration
{
    protected \$tableName = '{$tableName}';

    public function up()
    {
{$upMethod}
    }

    public function down()
    {
{$downMethod}
    }

    public function getVersion()
    {
        return '{$version}';
    }
}
PHP;
    }

    protected function generateCreateTableMethod($tableName, $fields, $indexes, $foreignKeys)
    {
        $fieldsArray = $this->formatFieldsForMigration($fields);
        $method = "        \$this->forge->addField([\n{$fieldsArray}\n        ]);\n\n";

        // Add primary key
        $primaryKey = $this->getPrimaryKey($tableName);
        if ($primaryKey) {
            $method .= "        \$this->forge->addPrimaryKey('{$primaryKey}');\n\n";
        }

        // Add indexes
        foreach ($indexes as $index) {
            if ($index['type'] === 'UNIQUE') {
                $method .= "        \$this->forge->addUniqueKey('{$index['column']}');\n";
            } else {
                $method .= "        \$this->forge->addKey('{$index['column']}');\n";
            }
        }

        if (!empty($indexes)) {
            $method .= "\n";
        }

        $method .= "        \$this->forge->createTable(\$this->tableName);\n\n";

        // Add foreign keys
        foreach ($foreignKeys as $fk) {
            $method .= "        \$this->db->query('ALTER TABLE {\$this->tableName} ADD CONSTRAINT {$fk['name']} FOREIGN KEY ({$fk['column']}) REFERENCES {$fk['referenced_table']}({$fk['referenced_column']})');\n";
        }

        return $method;
    }

    protected function generateUpdateTableMethod($tableName, $fields, $indexes, $foreignKeys)
    {
        $changes = $this->getSchemaChanges($tableName);

        if ($changes['type'] === 'new_table') {
            // Generate as a create table if it's completely new
            return $this->generateCreateTableMethod($tableName, $fields, $indexes, $foreignKeys);
        }

        if ($changes['type'] === 'dropped_table') {
            return "        \$this->forge->dropTable(\$this->tableName);";
        }

        $method = "        // Update table structure for {$tableName}\n\n";

        // Handle added fields
        if (!empty($changes['added_fields'])) {
            $method .= "        // Add new fields\n";
            $addFields = [];
            foreach ($changes['added_fields'] as $field) {
                $fieldDef = $this->formatSingleFieldForMigration($field);
                $addFields[] = $fieldDef;
            }
            $method .= "        \$this->forge->addColumn(\$this->tableName, [\n";
            $method .= implode(",\n", $addFields) . "\n";
            $method .= "        ]);\n\n";
        }

        // Handle modified fields
        if (!empty($changes['modified_fields'])) {
            $method .= "        // Modify existing fields\n";
            $modifyFields = [];
            foreach ($changes['modified_fields'] as $fieldChange) {
                $fieldDef = $this->formatSingleFieldForMigration($fieldChange['current']);
                $modifyFields[] = $fieldDef;
            }
            $method .= "        \$this->forge->modifyColumn(\$this->tableName, [\n";
            $method .= implode(",\n", $modifyFields) . "\n";
            $method .= "        ]);\n\n";
        }

        // Handle removed fields
        if (!empty($changes['removed_fields'])) {
            $method .= "        // Remove fields\n";
            foreach ($changes['removed_fields'] as $field) {
                $method .= "        \$this->forge->dropColumn(\$this->tableName, '{$field['name']}');\n";
            }
            $method .= "\n";
        }

        // Handle primary key changes
        if ($changes['primary_key_changed']) {
            if ($changes['old_primary_key']) {
                $method .= "        // Drop old primary key\n";
                $method .= "        \$this->db->query('ALTER TABLE {\$this->tableName} DROP PRIMARY KEY');\n";
            }
            if ($changes['new_primary_key']) {
                $method .= "        // Add new primary key\n";
                $method .= "        \$this->db->query('ALTER TABLE {\$this->tableName} ADD PRIMARY KEY ({$changes['new_primary_key']})');\n";
            }
            $method .= "\n";
        }

        // Handle removed indexes
        if (!empty($changes['removed_indexes'])) {
            $method .= "        // Remove indexes\n";
            foreach ($changes['removed_indexes'] as $index) {
                $method .= "        \$this->db->query('ALTER TABLE {\$this->tableName} DROP INDEX {$index['name']}');\n";
            }
            $method .= "\n";
        }

        // Handle added indexes
        if (!empty($changes['added_indexes'])) {
            $method .= "        // Add new indexes\n";
            foreach ($changes['added_indexes'] as $index) {
                if ($index['type'] === 'UNIQUE') {
                    $method .= "        \$this->forge->addUniqueKey('{$index['column']}');\n";
                } else {
                    $method .= "        \$this->forge->addKey('{$index['column']}');\n";
                }
            }
            $method .= "\n";
        }

        // Handle foreign key changes
        if (!empty($changes['removed_foreign_keys'])) {
            $method .= "        // Remove foreign keys\n";
            foreach ($changes['removed_foreign_keys'] as $fk) {
                $method .= "        \$this->db->query('ALTER TABLE {\$this->tableName} DROP FOREIGN KEY {$fk['name']}');\n";
            }
            $method .= "\n";
        }

        if (!empty($changes['added_foreign_keys'])) {
            $method .= "        // Add foreign keys\n";
            foreach ($changes['added_foreign_keys'] as $fk) {
                $method .= "        \$this->db->query('ALTER TABLE {\$this->tableName} ADD CONSTRAINT {$fk['name']} FOREIGN KEY ({$fk['column']}) REFERENCES {$fk['referenced_table']}({$fk['referenced_column']})');\n";
            }
            $method .= "\n";
        }

        if (trim($method) === "        // Update table structure for {$tableName}") {
            $method .= "        // No changes detected\n";
        }

        return $method;
    }

    protected function formatSingleFieldForMigration($field)
    {
        $fieldDef = "            '{$field['name']}' => [\n";
        $fieldDef .= "                'type' => '" . $this->mapFieldType($field['type']) . "',\n";

        if (isset($field['constraint']) && $field['constraint']) {
            $fieldDef .= "                'constraint' => {$field['constraint']},\n";
        }

        if ($field['null'] === 'NO') {
            $fieldDef .= "                'null' => false,\n";
        }

        if (isset($field['default']) && $field['default'] !== null) {
            $defaultValue = is_string($field['default']) ? "'{$field['default']}'" : $field['default'];
            $fieldDef .= "                'default' => {$defaultValue},\n";
        }

        if ($field['extra'] === 'auto_increment') {
            $fieldDef .= "                'auto_increment' => true,\n";
        }

        $fieldDef .= "            ]";

        return $fieldDef;
    }

    protected function generateDropTableMethod($tableName)
    {
        return "        \$this->forge->dropTable(\$this->tableName);";
    }

    protected function generateRevertUpdateMethod($tableName)
    {
        $changes = $this->getSchemaChanges($tableName);

        if ($changes['type'] === 'new_table') {
            return "        \$this->forge->dropTable(\$this->tableName);";
        }

        if ($changes['type'] === 'dropped_table') {
            // We can't easily recreate a dropped table without the original schema
            return "        // WARNING: Cannot automatically revert table drop\n        // You need to manually recreate the table structure";
        }

        $method = "        // Revert the changes made in up() method for {$tableName}\n\n";

        // Revert in reverse order

        // Revert added foreign keys
        if (!empty($changes['added_foreign_keys'])) {
            $method .= "        // Remove added foreign keys\n";
            foreach ($changes['added_foreign_keys'] as $fk) {
                $method .= "        \$this->db->query('ALTER TABLE {\$this->tableName} DROP FOREIGN KEY {$fk['name']}');\n";
            }
            $method .= "\n";
        }

        // Revert removed foreign keys
        if (!empty($changes['removed_foreign_keys'])) {
            $method .= "        // Restore removed foreign keys\n";
            foreach ($changes['removed_foreign_keys'] as $fk) {
                $method .= "        \$this->db->query('ALTER TABLE {\$this->tableName} ADD CONSTRAINT {$fk['name']} FOREIGN KEY ({$fk['column']}) REFERENCES {$fk['referenced_table']}({$fk['referenced_column']})');\n";
            }
            $method .= "\n";
        }

        // Revert added indexes
        if (!empty($changes['added_indexes'])) {
            $method .= "        // Remove added indexes\n";
            foreach ($changes['added_indexes'] as $index) {
                $method .= "        \$this->db->query('ALTER TABLE {\$this->tableName} DROP INDEX {$index['name']}');\n";
            }
            $method .= "\n";
        }

        // Revert removed indexes
        if (!empty($changes['removed_indexes'])) {
            $method .= "        // Restore removed indexes\n";
            foreach ($changes['removed_indexes'] as $index) {
                if ($index['type'] === 'UNIQUE') {
                    $method .= "        \$this->forge->addUniqueKey('{$index['column']}');\n";
                } else {
                    $method .= "        \$this->forge->addKey('{$index['column']}');\n";
                }
            }
            $method .= "\n";
        }

        // Revert primary key changes
        if ($changes['primary_key_changed']) {
            if ($changes['new_primary_key']) {
                $method .= "        // Remove new primary key\n";
                $method .= "        \$this->db->query('ALTER TABLE {\$this->tableName} DROP PRIMARY KEY');\n";
            }
            if ($changes['old_primary_key']) {
                $method .= "        // Restore old primary key\n";
                $method .= "        \$this->db->query('ALTER TABLE {\$this->tableName} ADD PRIMARY KEY ({$changes['old_primary_key']})');\n";
            }
            $method .= "\n";
        }

        // Revert added fields
        if (!empty($changes['added_fields'])) {
            $method .= "        // Remove added fields\n";
            foreach ($changes['added_fields'] as $field) {
                $method .= "        \$this->forge->dropColumn(\$this->tableName, '{$field['name']}');\n";
            }
            $method .= "\n";
        }

        // Revert modified fields
        if (!empty($changes['modified_fields'])) {
            $method .= "        // Restore original field definitions\n";
            $revertFields = [];
            foreach ($changes['modified_fields'] as $fieldChange) {
                $fieldDef = $this->formatSingleFieldForMigration($fieldChange['previous']);
                $revertFields[] = $fieldDef;
            }
            $method .= "        \$this->forge->modifyColumn(\$this->tableName, [\n";
            $method .= implode(",\n", $revertFields) . "\n";
            $method .= "        ]);\n\n";
        }

        // Revert removed fields (restore them)
        if (!empty($changes['removed_fields'])) {
            $method .= "        // Restore removed fields\n";
            $restoreFields = [];
            foreach ($changes['removed_fields'] as $field) {
                $fieldDef = $this->formatSingleFieldForMigration($field);
                $restoreFields[] = $fieldDef;
            }
            $method .= "        \$this->forge->addColumn(\$this->tableName, [\n";
            $method .= implode(",\n", $restoreFields) . "\n";
            $method .= "        ]);\n\n";
        }

        if (trim($method) === "        // Revert the changes made in up() method for {$tableName}") {
            $method .= "        // No changes to revert\n";
        }

        return $method;
    }

    protected function formatFieldsForMigration($fields)
    {
        $formatted = [];

        foreach ($fields as $field) {
            $fieldDef = "            '{$field['name']}' => [\n";
            $fieldDef .= "                'type' => '" . $this->mapFieldType($field['type']) . "',\n";

            if (isset($field['constraint']) && $field['constraint']) {
                $fieldDef .= "                'constraint' => {$field['constraint']},\n";
            }

            if ($field['null'] === 'NO') {
                $fieldDef .= "                'null' => false,\n";
            }

            if (isset($field['default']) && $field['default'] !== null) {
                $defaultValue = is_string($field['default']) ? "'{$field['default']}'" : $field['default'];
                $fieldDef .= "                'default' => {$defaultValue},\n";
            }

            if ($field['extra'] === 'auto_increment') {
                $fieldDef .= "                'auto_increment' => true,\n";
            }

            $fieldDef .= "            ],";
            $formatted[] = $fieldDef;
        }

        return implode("\n", $formatted);
    }

    protected function mapFieldType($mysqlType)
    {
        $typeMap = [
            'int' => 'INT',
            'bigint' => 'BIGINT',
            'varchar' => 'VARCHAR',
            'text' => 'TEXT',
            'longtext' => 'LONGTEXT',
            'datetime' => 'DATETIME',
            'timestamp' => 'TIMESTAMP',
            'date' => 'DATE',
            'time' => 'TIME',
            'decimal' => 'DECIMAL',
            'float' => 'FLOAT',
            'double' => 'DOUBLE',
            'tinyint' => 'TINYINT',
            'smallint' => 'SMALLINT',
            'mediumint' => 'MEDIUMINT',
            'char' => 'CHAR',
            'binary' => 'BINARY',
            'varbinary' => 'VARBINARY',
            'blob' => 'BLOB',
            'mediumblob' => 'MEDIUMBLOB',
            'longblob' => 'LONGBLOB',
            'enum' => 'ENUM',
            'set' => 'SET',
            'json' => 'JSON'
        ];

        $type = strtolower(preg_replace('/\(.*\)/', '', $mysqlType));
        return $typeMap[$type] ?? 'VARCHAR';
    }

    protected function getTables()
    {
        return $this->db->listTables();
    }

    protected function getTableFields($tableName)
    {
        $query = $this->db->query("DESCRIBE {$tableName}");
        $fields = [];

        foreach ($query->getResultArray() as $row) {
            $type = $row['Type'];
            $constraint = null;

            // Extract constraint from type
            if (preg_match('/\((\d+)\)/', $type, $matches)) {
                $constraint = $matches[1];
            }

            $fields[] = [
                'name' => $row['Field'],
                'type' => $type,
                'constraint' => $constraint,
                'null' => $row['Null'],
                'default' => $row['Default'],
                'extra' => $row['Extra']
            ];
        }

        return $fields;
    }

    protected function getTableIndexes($tableName)
    {
        $query = $this->db->query("SHOW INDEX FROM {$tableName}");
        $indexes = [];

        foreach ($query->getResultArray() as $row) {
            if ($row['Key_name'] !== 'PRIMARY') {
                $indexes[] = [
                    'name' => $row['Key_name'],
                    'column' => $row['Column_name'],
                    'type' => $row['Non_unique'] ? 'INDEX' : 'UNIQUE'
                ];
            }
        }

        return $indexes;
    }

    protected function getPrimaryKey($tableName)
    {
        $query = $this->db->query("SHOW INDEX FROM {$tableName} WHERE Key_name = 'PRIMARY'");
        $result = $query->getRowArray();
        return $result ? $result['Column_name'] : null;
    }

    protected function getForeignKeys($tableName)
    {
        $query = $this->db->query("
            SELECT
                CONSTRAINT_NAME as name,
                COLUMN_NAME as column,
                REFERENCED_TABLE_NAME as referenced_table,
                REFERENCED_COLUMN_NAME as referenced_column
            FROM information_schema.KEY_COLUMN_USAGE
            WHERE TABLE_SCHEMA = DATABASE()
                AND TABLE_NAME = '{$tableName}'
                AND REFERENCED_TABLE_NAME IS NOT NULL
        ");

        return $query->getResultArray();
    }

    protected function getClassName($tableName, $type)
    {
        $action = $type === 'create' ? 'Create' : 'Update';
        $tableName = str_replace('_', '', ucwords($tableName, '_'));
        return $action . $tableName . 'Table';
    }

    protected function hasSchemaChanges($tableName)
    {
        $currentSchema = $this->getCurrentTableSchema($tableName);
        $previousSchema = $this->getPreviousTableSchema($tableName);

        if (!$previousSchema) {
            // No previous schema found, this is a new table
            return true;
        }

        return $this->compareSchemas($currentSchema, $previousSchema);
    }

    protected function getCurrentTableSchema($tableName)
    {
        if (!$this->db->tableExists($tableName)) {
            return null;
        }

        return [
            'fields' => $this->getTableFields($tableName),
            'indexes' => $this->getTableIndexes($tableName),
            'foreign_keys' => $this->getForeignKeys($tableName),
            'primary_key' => $this->getPrimaryKey($tableName)
        ];
    }

    protected function getPreviousTableSchema($tableName)
    {
        $currentVersion = $this->getCurrentVersion();
        if ($currentVersion === '0.0.0') {
            return null;
        }

        // Look for the most recent migration file for this table
        $migrationFiles = glob($this->migrationPath . "v*_{$tableName}_*.php");
        if (empty($migrationFiles)) {
            return null;
        }

        // Sort by version to get the latest
        usort($migrationFiles, function($a, $b) {
            preg_match('/v(\d+\.\d+\.\d+)_/', basename($a), $matchesA);
            preg_match('/v(\d+\.\d+\.\d+)_/', basename($b), $matchesB);
            return version_compare($matchesA[1] ?? '0.0.0', $matchesB[1] ?? '0.0.0');
        });

        $latestFile = end($migrationFiles);
        return $this->extractSchemaFromMigrationFile($latestFile);
    }

    protected function extractSchemaFromMigrationFile($filePath)
    {
        $content = file_get_contents($filePath);

        // Extract field definitions from the migration file
        $schema = [
            'fields' => [],
            'indexes' => [],
            'foreign_keys' => [],
            'primary_key' => null
        ];

        // Parse addField array
        if (preg_match('/addField\(\[(.*?)\]\);/s', $content, $matches)) {
            $fieldsContent = $matches[1];
            $schema['fields'] = $this->parseFieldsFromMigration($fieldsContent);
        }

        // Parse primary key
        if (preg_match('/addPrimaryKey\([\'"]([^\'"]+)[\'"]\)/', $content, $matches)) {
            $schema['primary_key'] = $matches[1];
        }

        // Parse indexes
        preg_match_all('/add(?:Unique)?Key\([\'"]([^\'"]+)[\'"]\)/', $content, $indexMatches);
        foreach ($indexMatches[1] as $index) {
            $isUnique = strpos($content, "addUniqueKey('{$index}')") !== false;
            $schema['indexes'][] = [
                'name' => $index,
                'column' => $index,
                'type' => $isUnique ? 'UNIQUE' : 'INDEX'
            ];
        }

        // Parse foreign keys (simplified)
        preg_match_all('/ADD CONSTRAINT (\w+) FOREIGN KEY \((\w+)\) REFERENCES (\w+)\((\w+)\)/', $content, $fkMatches);
        for ($i = 0; $i < count($fkMatches[0]); $i++) {
            $schema['foreign_keys'][] = [
                'name' => $fkMatches[1][$i],
                'column' => $fkMatches[2][$i],
                'referenced_table' => $fkMatches[3][$i],
                'referenced_column' => $fkMatches[4][$i]
            ];
        }

        return $schema;
    }

    protected function parseFieldsFromMigration($fieldsContent)
    {
        $fields = [];

        // Split by field definitions
        preg_match_all('/[\'"](\w+)[\'"].*?\=\>\s*\[(.*?)\],/s', $fieldsContent, $matches, PREG_SET_ORDER);

        foreach ($matches as $match) {
            $fieldName = $match[1];
            $fieldConfig = $match[2];

            $field = ['name' => $fieldName];

            // Parse field properties
            if (preg_match('/[\'"]type[\'"].*?[\'"]([^\'"]+)[\'"]/', $fieldConfig, $typeMatch)) {
                $field['type'] = strtolower($typeMatch[1]);
            }

            if (preg_match('/[\'"]constraint[\'"].*?(\d+)/', $fieldConfig, $constraintMatch)) {
                $field['constraint'] = $constraintMatch[1];
            }

            if (preg_match('/[\'"]null[\'"].*?(false|true)/', $fieldConfig, $nullMatch)) {
                $field['null'] = $nullMatch[1] === 'false' ? 'NO' : 'YES';
            } else {
                $field['null'] = 'YES'; // Default
            }

            if (preg_match('/[\'"]default[\'"].*?[\'"]?([^\'"]+)[\'"]?/', $fieldConfig, $defaultMatch)) {
                $field['default'] = $defaultMatch[1];
            }

            if (strpos($fieldConfig, 'auto_increment') !== false) {
                $field['extra'] = 'auto_increment';
            } else {
                $field['extra'] = '';
            }

            $fields[] = $field;
        }

        return $fields;
    }

    protected function compareSchemas($current, $previous)
    {
        // Compare fields
        if ($this->compareFields($current['fields'], $previous['fields'])) {
            return true;
        }

        // Compare indexes
        if ($this->compareIndexes($current['indexes'], $previous['indexes'])) {
            return true;
        }

        // Compare foreign keys
        if ($this->compareForeignKeys($current['foreign_keys'], $previous['foreign_keys'])) {
            return true;
        }

        // Compare primary key
        if ($current['primary_key'] !== $previous['primary_key']) {
            return true;
        }

        return false;
    }

    protected function compareFields($currentFields, $previousFields)
    {
        // Create associative arrays for easier comparison
        $currentByName = [];
        foreach ($currentFields as $field) {
            $currentByName[$field['name']] = $field;
        }

        $previousByName = [];
        foreach ($previousFields as $field) {
            $previousByName[$field['name']] = $field;
        }

        // Check for new or modified fields
        foreach ($currentByName as $name => $field) {
            if (!isset($previousByName[$name])) {
                return true; // New field
            }

            $prevField = $previousByName[$name];

            // Compare field properties
            if ($this->normalizeFieldType($field['type']) !== $this->normalizeFieldType($prevField['type']) ||
                $field['constraint'] !== $prevField['constraint'] ||
                $field['null'] !== $prevField['null'] ||
                $field['default'] !== $prevField['default'] ||
                $field['extra'] !== $prevField['extra']) {
                return true; // Modified field
            }
        }

        // Check for removed fields
        foreach ($previousByName as $name => $field) {
            if (!isset($currentByName[$name])) {
                return true; // Removed field
            }
        }

        return false;
    }

    protected function compareIndexes($currentIndexes, $previousIndexes)
    {
        $currentKeys = array_map(function($idx) {
            return $idx['column'] . '_' . $idx['type'];
        }, $currentIndexes);

        $previousKeys = array_map(function($idx) {
            return $idx['column'] . '_' . $idx['type'];
        }, $previousIndexes);

        return $currentKeys !== $previousKeys;
    }

    protected function compareForeignKeys($currentFks, $previousFks)
    {
        $currentKeys = array_map(function($fk) {
            return $fk['column'] . '_' . $fk['referenced_table'] . '_' . $fk['referenced_column'];
        }, $currentFks);

        $previousKeys = array_map(function($fk) {
            return $fk['column'] . '_' . $fk['referenced_table'] . '_' . $fk['referenced_column'];
        }, $previousFks);

        return $currentKeys !== $previousKeys;
    }

    protected function normalizeFieldType($type)
    {
        // Remove constraints and normalize for comparison
        return strtolower(preg_replace('/\(.*?\)/', '', $type));
    }

    protected function getSchemaChanges($tableName)
    {
        $currentSchema = $this->getCurrentTableSchema($tableName);
        $previousSchema = $this->getPreviousTableSchema($tableName);

        if (!$previousSchema) {
            return ['type' => 'new_table', 'schema' => $currentSchema];
        }

        if (!$currentSchema) {
            return ['type' => 'dropped_table'];
        }

        $changes = [
            'type' => 'modified_table',
            'added_fields' => [],
            'modified_fields' => [],
            'removed_fields' => [],
            'added_indexes' => [],
            'removed_indexes' => [],
            'added_foreign_keys' => [],
            'removed_foreign_keys' => [],
            'primary_key_changed' => false
        ];

        // Analyze field changes
        $currentFieldsByName = [];
        foreach ($currentSchema['fields'] as $field) {
            $currentFieldsByName[$field['name']] = $field;
        }

        $previousFieldsByName = [];
        foreach ($previousSchema['fields'] as $field) {
            $previousFieldsByName[$field['name']] = $field;
        }

        // Find added and modified fields
        foreach ($currentFieldsByName as $name => $field) {
            if (!isset($previousFieldsByName[$name])) {
                $changes['added_fields'][] = $field;
            } else {
                $prevField = $previousFieldsByName[$name];
                if ($this->normalizeFieldType($field['type']) !== $this->normalizeFieldType($prevField['type']) ||
                    $field['constraint'] !== $prevField['constraint'] ||
                    $field['null'] !== $prevField['null'] ||
                    $field['default'] !== $prevField['default'] ||
                    $field['extra'] !== $prevField['extra']) {
                    $changes['modified_fields'][] = [
                        'previous' => $prevField,
                        'current' => $field
                    ];
                }
            }
        }

        // Find removed fields
        foreach ($previousFieldsByName as $name => $field) {
            if (!isset($currentFieldsByName[$name])) {
                $changes['removed_fields'][] = $field;
            }
        }

        // Analyze index changes
        $currentIndexKeys = array_map(function($idx) {
            return $idx['column'] . '_' . $idx['type'];
        }, $currentSchema['indexes']);

        $previousIndexKeys = array_map(function($idx) {
            return $idx['column'] . '_' . $idx['type'];
        }, $previousSchema['indexes']);

        foreach ($currentSchema['indexes'] as $index) {
            $key = $index['column'] . '_' . $index['type'];
            if (!in_array($key, $previousIndexKeys)) {
                $changes['added_indexes'][] = $index;
            }
        }

        foreach ($previousSchema['indexes'] as $index) {
            $key = $index['column'] . '_' . $index['type'];
            if (!in_array($key, $currentIndexKeys)) {
                $changes['removed_indexes'][] = $index;
            }
        }

        // Check primary key changes
        if ($currentSchema['primary_key'] !== $previousSchema['primary_key']) {
            $changes['primary_key_changed'] = true;
            $changes['old_primary_key'] = $previousSchema['primary_key'];
            $changes['new_primary_key'] = $currentSchema['primary_key'];
        }

        return $changes;
    }
}
