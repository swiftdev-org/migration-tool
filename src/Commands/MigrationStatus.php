<?php namespace Swift\Migration\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use Config\Database;

class MigrationStatus extends BaseCommand
{
    protected $group       = 'Database';
    protected $name        = 'db:migration-status';
    protected $description = 'Show current migration status and database schema version';

    public function run(array $params)
    {
        $db = Database::connect();
        $versionFile = APPPATH . 'Database/migration_version.json';

        CLI::write('Database Migration Status:', 'green');
        CLI::write(str_repeat('=', 60));

        // Show current schema version
        if (file_exists($versionFile)) {
            $versionData = json_decode(file_get_contents($versionFile), true);
            CLI::write("Current Schema Version: " . ($versionData['current_version'] ?? '0.0.0'), 'cyan');
            CLI::write("Last Updated: " . ($versionData['last_updated'] ?? 'Unknown'), 'cyan');
            CLI::newLine();
        } else {
            CLI::write("Current Schema Version: 0.0.0 (No version file found)", 'yellow');
            CLI::newLine();
        }

        // Check if CI migrations table exists
        if ($db->tableExists('migrations')) {
            $migrations = $db->table('migrations')
                ->orderBy('batch', 'DESC')
                ->orderBy('version', 'DESC')
                ->get()
                ->getResultArray();

            if (!empty($migrations)) {
                CLI::write('CodeIgniter Migration History:', 'green');
                CLI::write(str_repeat('-', 80));

                printf("%-20s %-40s %-10s\n", 'Version', 'Migration', 'Batch');
                CLI::write(str_repeat('-', 80));

                foreach ($migrations as $migration) {
                    printf("%-20s %-40s %-10s\n",
                        $migration['version'],
                        $migration['class'],
                        $migration['batch']
                    );
                }
                CLI::newLine();
            }
        } else {
            CLI::write('CodeIgniter migrations table not found.', 'yellow');
            CLI::newLine();
        }

        // List available migration files
        $migrationPath = APPPATH . 'Database/Migrations/';
        $files = glob($migrationPath . 'v*.php');

        if (!empty($files)) {
            CLI::write('Available Migration Files:', 'green');
            CLI::write(str_repeat('-', 40));

            $versions = [];
            foreach ($files as $file) {
                $filename = basename($file);
                if (preg_match('/^v(\d+\.\d+\.\d+)_/', $filename, $matches)) {
                    $version = $matches[1];
                    $versions[$version][] = $filename;
                }
            }

            ksort($versions, SORT_VERSION);

            foreach ($versions as $version => $files) {
                CLI::write("v{$version}:", 'cyan');
                foreach ($files as $file) {
                    CLI::write("  - {$file}", 'white');
                }
            }
        } else {
            CLI::write('No versioned migration files found.', 'yellow');
        }
    }
}
