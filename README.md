# Database Migration Manager for CodeIgniter 4

A comprehensive set of Spark commands for generating and managing database migrations with semantic versioning support. This tool automatically creates migration files from your existing database schema and provides advanced version management capabilities.

## Features

- 🏷️ **Semantic Versioning** - Uses standard software versioning (1.0.0, 1.1.0, 2.0.0)
- 🔄 **Smart Update System** - Update current version or create new versions intelligently
- 📊 **Schema Analysis** - Automatically detects tables, fields, indexes, and foreign keys
- 🔍 **Smart Change Detection** - Only includes actual schema differences in update migrations
- 📝 **Version History** - Track all migration versions with timestamps and descriptions
- 🎯 **Selective Generation** - Generate migrations for specific tables
- 🔍 **Status Monitoring** - View current version and migration status
- 🚀 **Reset Capability** - Complete migration reset for major restructuring

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

## Core Operations

### 1. **Update Current Version** (`--update`)
Modifies the current migration version with detected changes. Perfect for active development.

```bash
# Update current version with detected changes
php spark db:generate-migration --update

# Update with custom description
php spark db:generate-migration --update --description="Fixed user table constraints"

# Update specific table only
php spark db:generate-migration --update --table=users
```

**What it does:**
- Compares current database with existing migration files
- Overwrites current version files with detected changes
- Keeps the same version number
- Shows detailed change detection output

### 2. **Create New Version** (Version Increments)
Creates a new migration version with detected changes. Perfect for releases and milestones.

```bash
# Major version (breaking changes): 1.0.0 -> 2.0.0
php spark db:generate-migration --major --description="Complete API redesign"

# Minor version (new features): 1.0.0 -> 1.1.0
php spark db:generate-migration --minor --description="Added user preferences"

# Patch version (bug fixes): 1.0.0 -> 1.0.1
php spark db:generate-migration --patch --description="Fixed email validation"

# Custom version
php spark db:generate-migration --version=2.5.0 --description="Special release"
```

**What it does:**
- Creates new version files
- Preserves existing migration files
- Increments version number as specified
- Generates migrations with only detected changes

### 3. **Reset All Migrations** (`--reset`)
Nuclear option: deletes all migrations and starts fresh. Use with caution!

```bash
# Reset to v1.0.0 (with confirmation prompt)
php spark db:generate-migration --reset

# Reset with custom version
php spark db:generate-migration --reset --version=2.0.0 --description="Complete redesign"
```

**What it does:**
- Deletes ALL existing migration files
- Requires confirmation before proceeding
- Recreates complete table structures from current database
- Resets version history

## Command Options

| Option | Description |
|--------|-------------|
| `--table` | Generate migration for specific table only |
| `--update` | Update current migration version with detected changes (overwrites existing) |
| `--version` | Specify exact version (e.g., 1.2.0) |
| `--major` | Increment major version (breaking changes) |
| `--minor` | Increment minor version (new features) |
| `--patch` | Increment patch version (bug fixes) |
| `--reset` | Delete all previous migrations and start fresh with specified version |
| `--force` | Force overwrite existing migration files |
| `--description` | Add custom description for this migration version |

## Smart Schema Change Detection

The system intelligently compares your current database schema with the previous migration files to detect only actual changes:

### Detected Changes Include:
- **Field Changes**: New fields, modified field types/constraints, removed fields
- **Index Changes**: Added/removed indexes and unique constraints
- **Primary Key Changes**: Modified primary key definitions
- **Foreign Key Changes**: Added/removed foreign key relationships
- **Table Changes**: New tables, dropped tables

### Change Detection Output:
When running migrations, you'll see detailed output about what changes were detected:

```bash
php spark db:generate-migration --minor --description="Added user preferences"

Generating new migration v1.2.0 by comparing database schema...
Description: Added user preferences
  └─ Changes detected for users:
    ├─ Added fields: preferences, avatar_url
    ├─ Modified fields: email
    ├─ Added indexes: email (UNIQUE)
    └─ Primary key changed: user_id → id
  └─ New table detected: user_preferences
No changes detected for table: posts
New migration v1.2.0 generation completed!
```

### Generated Update Migrations

Instead of recreating entire tables, update migrations contain only the specific changes:

```php
public function up()
{
    // Update table structure for users

    // Add new fields
    $this->forge->addColumn($this->tableName, [
        'preferences' => [
            'type' => 'JSON',
            'null' => true,
        ],
        'avatar_url' => [
            'type' => 'VARCHAR',
            'constraint' => 255,
            'null' => true,
        ]
    ]);

    // Modify existing fields
    $this->forge->modifyColumn($this->tableName, [
        'email' => [
            'type' => 'VARCHAR',
            'constraint' => 320,
            'null' => false,
        ]
    ]);

    // Add new indexes
    $this->forge->addUniqueKey('email');
}

public function down()
{
    // Revert the changes made in up() method for users

    // Remove added indexes
    $this->db->query('ALTER TABLE {$this->tableName} DROP INDEX email');

    // Restore original field definitions
    $this->forge->modifyColumn($this->tableName, [
        'email' => [
            'type' => 'VARCHAR',
            'constraint' => 255,
            'null' => false,
        ]
    ]);

    // Remove added fields
    $this->forge->dropColumn($this->tableName, 'preferences');
    $this->forge->dropColumn($this->tableName, 'avatar_url');
}
```

## Usage Patterns

### Development Workflow

#### Option 1: Update Current Version (Recommended for Active Development)
```bash
# Start with initial version
php spark db:generate-migration --version=1.0.0 --description="Initial schema"

# Make database changes, then update current version
php spark db:generate-migration --update --description="Added user preferences"

# Continue making changes and updating same version
php spark db:generate-migration --update --description="Added social features"

# When ready for release, create new version
php spark db:generate-migration --minor --description="User management v1.1.0 ready"
```

#### Option 2: Create New Versions for Each Change
```bash
# Initial version
php spark db:generate-migration --version=1.0.0 --description="Initial schema"

# Each change gets a new version
php spark db:generate-migration --patch --description="Fixed email constraints"
php spark db:generate-migration --minor --description="Added user preferences"
php spark db:generate-migration --patch --description="Fixed index performance"
```

#### Option 3: Complete Reset (Nuclear Option)
```bash
# When you want to completely start over
php spark db:generate-migration --reset --version=2.0.0 --description="Complete redesign"
```

### Version Management Commands

```bash
# Show current version information
php spark db:migration-version show

# Set version manually with description
php spark db:migration-version set --version=3.0.0 --description="Major refactor with breaking changes"

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
    "description": "Added user roles and permissions system",
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

### `db:migration-version`

| Action | Description |
|--------|-------------|
| `show` | Display current version information |
| `set --version=X.Y.Z --description="desc"` | Manually set version with description |
| `history` | Show complete version history |
| `list` | List all migration files by version |

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

### Understanding the Options
- **`--update`**: Modifies current version - use during active development
- **`--major/minor/patch`**: Creates new versions - use for releases and milestones  
- **`--reset`**: Nuclear option - deletes everything and starts fresh

### Version Numbering
- **Major (X.0.0)**: Breaking changes, incompatible schema changes
- **Minor (X.Y.0)**: New features, backward-compatible additions
- **Patch (X.Y.Z)**: Bug fixes, small corrections

### Schema Change Detection
- The system compares current database state with previous migration files
- Only actual differences are included in update migrations
- Supports incremental schema evolution without recreating entire tables
- Automatically generates proper rollback logic in `down()` methods

### Development Workflow
- Use version increments (`--major`, `--minor`, `--patch`) to begin developing a new version
- Use `--update` during active development to refine the current version
- Use `--reset` sparingly when you need to completely restructure
- Always backup before running migrations
- Test migrations in development environment first

### File Management
- Keep migration files in version control
- Don't manually edit generated files  
- Use `--force` carefully to avoid losing custom changes
- Always backup before using `--reset`

## Troubleshooting

### Common Issues

**"No changes detected"**
```bash
# When no actual schema differences are found
php spark db:generate-migration --update
# Output: No schema changes detected.
```

**"Migration file already exists"**
```bash
# Use --force to overwrite
php spark db:generate-migration --update --force
```

**"Invalid version format"**
```bash
# Ensure semantic versioning format
php spark db:generate-migration --version=1.2.3  # ✓ Correct
php spark db:generate-migration --version=v1.2   # ✗ Invalid
```

**"No current version found"**
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
