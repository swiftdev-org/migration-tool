<?php namespace Swift\Migration\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

class MigrationVersion extends BaseCommand
{
    protected $group       = 'Database';
    protected $name        = 'db:migration-version';
    protected $description = 'Manage migration versions';

    protected $usage = 'db:migration-version [action] [options]';
    protected $arguments = [
        'action' => 'Action to perform: show, set, history, list'
    ];
    protected $options = [
        '--version' => 'Set specific version',
        '--list'    => 'List all available migration files',
        '--description' => 'Add custom description when setting version',
    ];

    protected $versionFile;

    public function run(array $params)
    {
        $this->versionFile = APPPATH . 'Database/migration_version.json';

        $action = $params[0] ?? 'show';

        switch ($action) {
            case 'show':
                $this->showCurrentVersion();
                break;
            case 'set':
                $this->setVersion();
                break;
            case 'history':
                $this->showHistory();
                break;
            case 'list':
                $this->listMigrations();
                break;
            default:
                $this->showHelp();
        }
    }

    protected function showCurrentVersion()
    {
        if (!file_exists($this->versionFile)) {
            CLI::write('No version file found. Current version: 0.0.0', 'yellow');
            return;
        }

        $versionData = json_decode(file_get_contents($this->versionFile), true);

        CLI::write('Current Migration Version Information:', 'green');
        CLI::write(str_repeat('-', 50));
        CLI::write("Version: " . ($versionData['current_version'] ?? '0.0.0'), 'cyan');
        CLI::write("Last Updated: " . ($versionData['last_updated'] ?? 'Unknown'), 'cyan');
        CLI::write("Description: " . ($versionData['description'] ?? 'N/A'), 'cyan');
    }

    protected function setVersion()
    {
        $version = CLI::getOption('version');
        $description = CLI::getOption('description');

        if (!$version) {
            CLI::error('Please provide a version using --version option');
            return;
        }

        if (!preg_match('/^\d+\.\d+\.\d+$/', $version)) {
            CLI::error('Invalid version format. Use semantic versioning (e.g., 1.2.3)');
            return;
        }

        $finalDescription = $description ?: 'Manually set version';

        $versionData = [
            'current_version' => $version,
            'last_updated' => date('Y-m-d H:i:s'),
            'description' => $finalDescription,
            'history' => []
        ];

        if (file_exists($this->versionFile)) {
            $existing = json_decode(file_get_contents($this->versionFile), true);
            if (isset($existing['history'])) {
                $versionData['history'] = $existing['history'];
            }
            // Add current version to history if it's different
            if (isset($existing['current_version']) && $existing['current_version'] !== $version) {
                $versionData['history'][] = [
                    'version' => $existing['current_version'],
                    'date' => $existing['last_updated'] ?? date('Y-m-d H:i:s'),
                    'description' => $existing['description'] ?? ''
                ];
            }
        }

        file_put_contents($this->versionFile, json_encode($versionData, JSON_PRETTY_PRINT));
        CLI::write("Version set to: {$version}", 'green');
        if ($description) {
            CLI::write("Description: {$finalDescription}", 'cyan');
        }
    }

    protected function showHistory()
    {
        if (!file_exists($this->versionFile)) {
            CLI::write('No version history found.', 'yellow');
            return;
        }

        $versionData = json_decode(file_get_contents($this->versionFile), true);
        $history = $versionData['history'] ?? [];

        if (empty($history)) {
            CLI::write('No version history available.', 'yellow');
            return;
        }

        CLI::write('Version History:', 'green');
        CLI::write(str_repeat('-', 70));

        printf("%-12s %-20s %-30s\n", 'Version', 'Date', 'Description');
        CLI::write(str_repeat('-', 70));

        foreach ($history as $entry) {
            printf("%-12s %-20s %-30s\n",
                $entry['version'] ?? 'Unknown',
                $entry['date'] ?? 'Unknown',
                $entry['description'] ?? 'N/A'
            );
        }

        // Show current version
        CLI::write(str_repeat('-', 70));
        printf("%-12s %-20s %-30s (Current)\n",
            $versionData['current_version'] ?? '0.0.0',
            $versionData['last_updated'] ?? 'Unknown',
            $versionData['description'] ?? 'N/A'
        );
    }

    protected function listMigrations()
    {
        $migrationPath = APPPATH . 'Database/Migrations/';
        $files = glob($migrationPath . 'v*.php');

        if (empty($files)) {
            CLI::write('No migration files found.', 'yellow');
            return;
        }

        CLI::write('Available Migration Files:', 'green');
        CLI::write(str_repeat('-', 60));

        $versions = [];
        foreach ($files as $file) {
            $filename = basename($file);
            if (preg_match('/^v(\d+\.\d+\.\d+)_(.+)\.php$/', $filename, $matches)) {
                $version = $matches[1];
                $table = str_replace('_create', '', str_replace('_update', '', $matches[2]));
                $versions[$version][] = $table;
            }
        }

        ksort($versions, SORT_VERSION);

        foreach ($versions as $version => $tables) {
            CLI::write("Version {$version}:", 'cyan');
            foreach ($tables as $table) {
                CLI::write("  - {$table}", 'white');
            }
            CLI::newLine();
        }
    }

    function showHelp()
    {
        CLI::write('Available actions:', 'green');
        CLI::write('  show    - Show current version information');
        CLI::write('  set     - Set version manually (use --version and optionally --description)');
        CLI::write('  history - Show version history');
        CLI::write('  list    - List all migration files by version');
    }
}
