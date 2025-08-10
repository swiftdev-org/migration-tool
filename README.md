# Database Migration Manager for CodeIgniter 4

A comprehensive set of Spark commands for generating and managing database migrations with semantic versioning support. This tool automatically creates migration files from your existing database schema and provides advanced version management capabilities.

## Features

- 🏷️ **Semantic Versioning** - Uses standard software versioning (1.0.0, 1.1.0, 2.0.0)
- 🔄 **Regeneration Support** - Rebuild current version when schema changes
- 📊 **Schema Analysis** - Automatically detects tables, fields, indexes, and foreign keys
- 📝 **Version History** - Track all migration versions with timestamps and descriptions
- 🎯 **Selective Generation** - Generate migrations for specific tables
- 🔍 **Status Monitoring** - View current version and migration status

## Installation

1. Copy the command files to your CodeIgniter 4 application:

```
app/Commands/
├── GenerateMigration.php
├── MigrationVersion.php
└── MigrationStatus.php
```

2. Ensure your `app/Database/Migrations/` directory exists and is writable

3. The commands will be automatically available via Spark CLI

## Commands Overview

| Command | Description |
|---------|-------------|
| `db:generate-migration` | Generate migration files from database schema |
| `db:migration-version` | Manage migration versions |
| `db:migration-status` | Show migration status and version info |

## Usage

### Generate Initial Migration

Create your first migration from existing database schema:

```bash
# Generate v1.0.0 migration for all tables
php spark db:generate-migration --version=1.0.0

# Generate for specific table only
php spark db:generate-migration --version=1.0.0 --table=users
```

### Version Management

#### Auto-increment Versions

```bash
# Major version (breaking changes): 1.0.0 -> 2.0.0
php spark db:generate-migration --major

# Minor version (new features): 1.0.0 -> 1.1.0
php spark db:generate-migration --minor

# Patch version (bug fixes): 1.0.0 -> 1.0.1
php spark db:generate-migration --patch

# Default behavior (minor increment)
php spark db:generate-migration
```

#### Custom Versions

```bash
# Set specific version
php spark db:generate-migration --version=2.5.0

# Generate update migration
php spark db:generate-migration --update --version=1.2.0
```

### Regenerate Current Version

When your database schema changes but you want to keep the same version:

```bash
# Regenerate all tables for current version
php spark db:generate-migration --regenerate

# Regenerate specific table
php spark db:generate-migration --regenerate --table=users

# Force overwrite existing files
php spark db:generate-migration --regenerate --force
```

### Version Management Commands

```bash
# Show current version information
php spark db:migration-version show

# Set version manually
php spark db:migration-version set --version=3.0.0

# View version history
php spark db:migration-version history

# List all migration files by version
php spark db:migration-version list
```

### Status and Monitoring

```bash
# View comprehensive migration status
php spark db:migration-status
```

## Command Options

### `db:generate-migration`

| Option | Description |
|--------|-------------|
| `--table` | Generate migration for specific table only |
| `--update` | Generate update migration by comparing schemas |
| `--version` | Specify exact version (e.g., 1.2.0) |
| `--major` | Increment major version (breaking changes) |
| `--minor` | Increment minor version (new features) |
| `--patch` | Increment patch version (bug fixes) |
| `--regenerate` | Regenerate current version (overwrites existing) |
| `--force` | Force overwrite existing migration files |

### `db:migration-version`

| Action | Description |
|--------|-------------|
| `show` | Display current version information |
| `set --version=X.Y.Z` | Manually set version |
| `history` | Show complete version history |
| `list` | List all migration files by version |

## File Structure

### Generated Files

Migration files are created with semantic version prefixes:

```
app/Database/Migrations/
├── v1.0.0_users_create.php
├── v1.0.0_posts_create.php
├── v1.1.0_users_update.php
└── v2.0.0_comments_create.php
```

### Version Tracking

Version information is stored in:

```
app/Database/migration_version.json
```

Example content:
```json
{
    "current_version": "1.2.0",
    "last_updated": "2024-08-10 15:30:00",
    "description": "Added user roles and permissions",
    "history": [
        {
            "version": "1.1.0",
            "date": "2024-08-05 10:15:00",
            "description": "Initial user management features"
        },
        {
            "version": "1.0.0",
            "date": "2024-08-01 09:00:00",
            "description": "Initial database schema migration"
        }
    ]
}
```

## Generated Migration Example

```php
<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Migration for users table
 * Version: 1.2.0
 * Generated: 2024-08-10 15:30:00
 */
class CreateUsersTable extends Migration
{
    protected $tableName = 'users';

    public function up()
    {
        $this->forge->addField([
            'id' => [
                'type' => 'INT',
                'constraint' => 11,
                'auto_increment' => true,
            ],
            'username' => [
                'type' => 'VARCHAR',
                'constraint' => 50,
                'null' => false,
            ],
            'email' => [
                'type' => 'VARCHAR',
                'constraint' => 255,
                'null' => false,
            ],
            'created_at' => [
                'type' => 'DATETIME',
                'null' => false,
            ],
        ]);

        $this->forge->addPrimaryKey('id');
        $this->forge->addUniqueKey('email');
        $this->forge->addKey('username');

        $this->forge->createTable($this->tableName);
    }

    public function down()
    {
        $this->forge->dropTable($this->tableName);
    }

    public function getVersion()
    {
        return '1.2.0';
    }
}
```

## Workflow Examples

### Starting a New Project

```bash
# 1. Generate initial migration from existing database
php spark db:generate-migration --version=1.0.0

# 2. Check status
php spark db:migration-status

# 3. Run migrations
php spark migrate
```

### Adding New Features

```bash
# 1. Make database changes manually
# 2. Generate update migration
php spark db:generate-migration --minor --update

# 3. Check what was generated
php spark db:migration-version list

# 4. Run new migrations
php spark migrate
```

### Schema Changes During Development

```bash
# 1. Modify database schema
# 2. Regenerate current version
php spark db:generate-migration --regenerate

# 3. Reset and re-run migrations
php spark migrate:reset
php spark migrate
```

## Supported Database Features

### Field Types
- All MySQL data types (INT, VARCHAR, TEXT, DATETIME, etc.)
- Constraints (length, precision)
- NULL/NOT NULL settings
- Default values
- Auto-increment fields

### Indexes
- Primary keys
- Unique indexes
- Regular indexes
- Composite indexes

### Relationships
- Foreign key constraints
- Referenced table and column detection

## Best Practices

### Version Numbering
- **Major (X.0.0)**: Breaking changes, incompatible schema changes
- **Minor (X.Y.0)**: New features, backward-compatible additions
- **Patch (X.Y.Z)**: Bug fixes, small corrections

### Development Workflow
1. Use `--regenerate` during active development
2. Use version increments for releases
3. Always backup before running migrations
4. Test migrations in development environment first

### File Management
- Keep migration files in version control
- Don't manually edit generated files
- Use `--force` carefully to avoid losing custom changes

## Troubleshooting

### Common Issues

**"Migration file already exists"**
```bash
# Use --force to overwrite
php spark db:generate-migration --regenerate --force
```

**"Invalid version format"**
```bash
# Ensure semantic versioning format
php spark db:generate-migration --version=1.2.3  # ✓ Correct
php spark db:generate-migration --version=v1.2   # ✗ Invalid
```

**"No version file found"**
```bash
# Initialize with first version
php spark db:migration-version set --version=1.0.0
```

### Debug Information

```bash
# Check current status
php spark db:migration-status

# View version history
php spark db:migration-version history

# List all available files
php spark db:migration-version list
```

## Contributing

When contributing to this project:

1. Follow semantic versioning principles
2. Test with various database schemas
3. Ensure backward compatibility
4. Update documentation for new features

## License

This tool is provided as-is for CodeIgniter 4 projects. Modify and distribute as needed for your projects.
