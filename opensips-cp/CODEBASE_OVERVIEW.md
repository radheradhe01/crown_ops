# OpenSIPS Control Panel (OCP) - Codebase Overview

## Executive Summary

OpenSIPS Control Panel (OCP) is a web-based provisioning and management portal for OpenSIPS SIP servers. It's a PHP-based application that provides a unified interface for managing multiple OpenSIPS instances, their configurations, users, and monitoring statistics.

## Architecture Overview

### Technology Stack
- **Backend**: PHP (PDO for database access)
- **Database**: MySQL/PostgreSQL/SQLite/Oracle (configurable)
- **Frontend**: HTML, CSS, JavaScript (with D3.js for charts)
- **Communication**: JSON-RPC over HTTP for MI (Management Interface) commands
- **Authentication**: Session-based with optional 2FA (Google Authenticator)

### Directory Structure

```
opensips-cp/
├── config/              # Configuration files
│   ├── db.inc.php      # Main database connection config
│   ├── globals.php     # Global configuration parameters
│   ├── local.inc.php   # Localizable strings (login labels, etc.)
│   ├── modules.inc.php # Module/tool registry and enable/disable
│   ├── session.inc.php # Session management
│   ├── boxes.load.php # Multi-server/box configuration loader
│   ├── db_schema.mysql # Database schema for OCP tables
│   └── tools/          # Tool-specific configurations
│       ├── admin/      # Admin tool configs (boxes, db, users)
│       ├── system/     # System tool configs (dispatcher, drouting, etc.)
│       └── users/      # User management tool configs
│
├── web/                # Web-accessible files (document root)
│   ├── index.php       # Login page
│   ├── login.php       # Authentication handler
│   ├── main.php        # Main application frame (frameset)
│   ├── menu.php        # Sidebar menu generator
│   ├── header.php      # Top header bar
│   ├── db_connect.php  # Database connection helper
│   ├── common/         # Shared utilities
│   │   ├── cfg_comm.php    # Configuration communication functions
│   │   ├── mi_comm.php     # Management Interface communication
│   │   ├── forms.php       # Form utilities
│   │   └── tools/tviewer/  # Tool viewer framework
│   └── tools/          # Tool implementations
│       ├── admin/      # Admin tools (boxes config, db config, list admins)
│       ├── system/     # System tools (dispatcher, drouting, dashboard, etc.)
│       └── users/      # User management tools
│
├── cron_job/           # Cron job scripts
│   ├── get_opensips_stats.php  # Statistics collection
│   ├── cdr_export.php           # CDR export
│   └── clean_statistics.php     # Statistics cleanup
│
└── googleAuth/         # 2FA authentication library
```

## Core Components

### 1. Authentication & Authorization

**Files**: `web/login.php`, `web/auth_login.php`, `web/auth_index.php`

**Features**:
- Username/password authentication (plain text or MD5 HA1 hash)
- Account lockout after failed attempts (configurable: default 3 attempts, 60s block)
- Optional two-factor authentication (Google Authenticator)
- Session-based authorization
- Permission levels: read-only, read-write, admin

**Database Tables**:
- `ocp_admin_privileges`: Stores admin users, passwords, permissions, 2FA secrets

**Flow**:
1. User submits credentials → `login.php`
2. Validates against `ocp_admin_privileges` table
3. If 2FA enabled → redirects to `auth_index.php`
4. On success → sets session variables (`user_login`, `user_tabs`, `user_priv`)
5. Redirects to `main.php` (main application)

### 2. Multi-Server Management (Boxes)

**Files**: `config/boxes.load.php`, `config/boxes.global.inc.php`

**Concept**: OCP can manage multiple OpenSIPS instances (called "boxes")

**Database Tables**:
- `ocp_boxes_config`: Stores box configurations (name, MI connection, monitoring settings)
- `ocp_system_config`: Groups boxes into systems (assoc_id)

**Box Configuration**:
- `mi_conn`: Management Interface connection string (format: `json:host:port/mi`)
- `monit_conn`: Monitoring connection (optional)
- `assoc_id`: Links box to a system
- `smonitcharts`: Enable/disable statistics charts

**Loading**: Boxes are loaded into `$_SESSION['boxes']` on first access and cached

### 3. Tool/Module System

**Files**: `config/modules.inc.php`, `web/common/cfg_comm.php`

**Structure**: Tools are organized into groups:
- **Dashboard**: Main dashboard with widgets
- **Users**: User management, aliases, groups
- **System**: All OpenSIPS management tools (dispatcher, drouting, etc.)
- **Admin**: System administration (boxes config, db config, admin management)

**Module Registration**:
```php
$config_modules = array(
    "system" => array(
        "enabled" => true,
        "name" => "System",
        "modules" => array(
            "dispatcher" => array(
                "enabled" => true,
                "name" => "Dispatcher"
            )
        )
    )
);
```

**Tool Structure**:
Each tool typically has:
- `web/tools/{group}/{tool}/index.php` - Entry point
- `web/tools/{group}/{tool}/{tool}.php` - Main logic
- `web/tools/{group}/{tool}/template/` - View templates
- `web/tools/{group}/{tool}/lib/` - JavaScript and helpers
- `config/tools/{group}/{tool}/settings.inc.php` - Tool configuration
- `config/tools/{group}/{tool}/db.inc.php` - Database config (optional)

### 4. Management Interface (MI) Communication

**File**: `web/common/mi_comm.php`

**Function**: Communicates with OpenSIPS via JSON-RPC over HTTP

**Key Functions**:
- `mi_command($command, $params_array, $mi_url, &$errors)`: Sends MI command
- `write2json($command, $params_array, $json_url, &$errors)`: HTTP JSON-RPC call

**MI URL Format**: `json:127.0.0.1:8888/mi`

**Example**:
```php
$errors = array();
$result = mi_command("get_statistics", array("all"), $box['mi']['conn'], $errors);
```

### 5. Configuration Management

**Files**: `web/common/cfg_comm.php`, `config/tools/*/settings.inc.php`

**System**: Two-level configuration:
1. **Tool-level settings**: Stored in `ocp_tools_config` table (per-tool, per-box)
2. **Global settings**: In `config/globals.php`

**Settings Structure**:
```php
$config->dispatcher = array(
    "table_dispatcher" => array(
        "default" => "dispatcher",
        "name" => "Table Dispatcher",
        "type" => "text",
        "tip" => "The database table name"
    )
);
```

**Loading**: Settings loaded via `session_load()` or `session_load_from_tool()`, cached in `$_SESSION['config']`

### 6. Database Management

**Files**: `web/db_connect.php`, `config/db.inc.php`

**Features**:
- Multiple database configurations (stored in `ocp_db_config` table)
- PDO-based connections
- Support for MySQL, PostgreSQL, SQLite, Oracle
- Default config in `config/db.inc.php`

**Database Configs**:
- Main OCP database: Stores OCP-specific data (admins, boxes, tools config)
- OpenSIPS databases: Each tool can connect to different OpenSIPS databases

### 7. Tool Viewer (TViewer) Framework

**Location**: `web/common/tools/tviewer/`

**Purpose**: Standardized framework for creating CRUD tools

**Components**:
- Standard templates for list, add, edit, delete operations
- JavaScript helpers for AJAX operations
- Form generation utilities

**Creating a TViewer Tool**:
1. Create tool directory in `web/tools/{group}/{tool}/`
2. Create `index.php` that redirects to main tool file
3. Create config files in `config/tools/{group}/{tool}/`:
   - `settings.inc.php`: Tool configuration
   - `db.inc.php`: Database connection (optional)
   - `tviewer.inc.php`: TViewer-specific config
4. Register in `config/modules.inc.php`

### 8. Dashboard System

**Location**: `web/tools/system/dashboard/`

**Features**:
- Widget-based dashboard
- Multiple panels (tabs)
- Drag-and-drop widget positioning
- Widget types: statistics, CDR, dispatcher status, etc.

**Database**: `ocp_dashboard` table stores panel configurations (JSON)

**Widgets**: Each tool can provide dashboard widgets in `template/dashboard_widgets/`

### 9. Statistics Monitoring

**Files**: `cron_job/get_opensips_stats.php`, `web/tools/system/smonitor/`

**Process**:
1. Cron job runs periodically (default: every minute)
2. Fetches statistics from OpenSIPS via MI
3. Stores in `ocp_monitoring_stats` table
4. Web interface displays charts using D3.js

**Database Tables**:
- `ocp_monitored_stats`: Which stats to monitor (per box)
- `ocp_monitoring_stats`: Historical statistics data
- `ocp_extra_stats`: Custom statistics classes

**Charting**: Uses D3.js library (`web/common/charting/`)

### 10. Permission System

**Files**: `web/common/cfg_comm.php` (get_priv function)

**Levels**:
- **read-only**: View only, no modifications
- **read-write**: Can add/edit/delete records
- **admin**: Full access including tool configuration

**Implementation**:
- Permissions stored in `ocp_admin_privileges.permissions` (comma-separated or "all")
- `available_tools`: Which tools user can access (comma-separated or "all")
- Checked via `get_priv($tool_name)` function
- Sets `$_SESSION['read_only']` and `$_SESSION['permission']`

## Key Workflows

### 1. User Login Flow
```
index.php (login form)
  ↓
login.php (validate credentials)
  ↓
[If 2FA enabled] auth_index.php → auth_login.php
  ↓
main.php (load main application)
  ↓
menu.php (generate sidebar)
  ↓
Tool index.php (load tool)
```

### 2. Tool Access Flow
```
User clicks menu item
  ↓
Tool index.php loads
  ↓
get_priv() checks permissions
  ↓
session_load() loads tool config
  ↓
Tool main file executes
  ↓
Template renders
```

### 3. MI Command Flow
```
Tool needs OpenSIPS data
  ↓
mi_command() called with command name
  ↓
write2json() creates JSON-RPC request
  ↓
cURL sends HTTP POST to OpenSIPS MI
  ↓
Response parsed and returned
```

### 4. Statistics Collection Flow
```
Cron job runs (get_opensips_stats.php)
  ↓
Loads boxes from database
  ↓
For each box with smonitor enabled:
  ↓
  Fetches monitored stats list
  ↓
  Calls MI get_statistics command
  ↓
  Parses response
  ↓
  Inserts into ocp_monitoring_stats
```

## Security Features

1. **CSRF Protection**: Token-based CSRF protection (`csrfguard_*` functions)
2. **Account Lockout**: Prevents brute force attacks
3. **Password Hashing**: Optional MD5 HA1 hashing
4. **Two-Factor Authentication**: Google Authenticator support
5. **Session Management**: Session validation on each request
6. **Permission Checks**: Tool-level and action-level permissions

## Database Schema

### Core Tables

1. **ocp_admin_privileges**: Admin users and permissions
2. **ocp_boxes_config**: OpenSIPS server configurations
3. **ocp_system_config**: System/group definitions
4. **ocp_tools_config**: Tool-specific configurations
5. **ocp_db_config**: Database connection configurations
6. **ocp_dashboard**: Dashboard panel configurations
7. **ocp_monitored_stats**: Statistics to monitor
8. **ocp_monitoring_stats**: Historical statistics data
9. **ocp_extra_stats**: Custom statistics definitions

## Extension Points

### Adding a New Tool

1. Create directory structure:
   - `web/tools/{group}/{tool}/`
   - `config/tools/{group}/{tool}/`

2. Create configuration file: `config/tools/{group}/{tool}/settings.inc.php`

3. Create entry point: `web/tools/{group}/{tool}/index.php`

4. Register in `config/modules.inc.php`

5. (Optional) Create database schema if needed

6. (Optional) Add dashboard widget

### Adding a Dashboard Widget

1. Create widget class in `web/tools/{group}/{tool}/template/dashboard_widgets/{widget}.php`
2. Widget class must have static properties and methods
3. Widget automatically appears in dashboard widget selection

### Custom Statistics

1. Create statistics class extending base class
2. Register in `ocp_extra_stats` table
3. Statistics automatically collected by cron job

## Configuration Files Reference

### config/db.inc.php
- Database connection settings (host, user, password, name, driver)

### config/globals.php
- Global settings (permissions array, lockout settings, 2FA config)

### config/local.inc.php
- Localizable strings (login labels, page titles)

### config/modules.inc.php
- Tool/module registry and enable/disable flags

### config/tools/*/settings.inc.php
- Tool-specific configuration parameters
- Defines configurable options with types, defaults, validation

## Common Patterns

### Tool Pattern (CRUD)
```php
// index.php
require("../../../common/cfg_comm.php");
session_start();
get_priv("tool_name");
header("Location: tool_name.php");

// tool_name.php
require("../../../common/cfg_comm.php");
require("template/header.php");
session_load();

if ($action == "add") { /* show add form */ }
if ($action == "do_add") { /* process add */ }
if ($action == "edit") { /* show edit form */ }
if ($action == "do_edit") { /* process edit */ }
if ($action == "delete") { /* delete record */ }

// Display list
require("template/tool_name.main.php");
require("template/footer.php");
```

### MI Command Pattern
```php
require("../../../common/mi_comm.php");
$errors = array();
$result = mi_command("command_name", $params, $box['mi']['conn'], $errors);
if (!empty($errors)) {
    // Handle errors
}
```

### Database Query Pattern
```php
require("lib/db_connect.php"); // Tool-specific DB connection
$sql = "SELECT * FROM table WHERE id = ?";
$stm = $link->prepare($sql);
$stm->execute(array($id));
$resultset = $stm->fetchAll(PDO::FETCH_ASSOC);
```

## Dependencies

### PHP Extensions
- PDO (database access)
- curl (MI communication)
- gd (image processing for charts)
- json (JSON handling)

### External Libraries
- D3.js (charting)
- Google Authenticator (2FA)

## Deployment

### Requirements
- Web server (Apache recommended)
- PHP 5.6+ with required extensions
- Database server (MySQL/PostgreSQL)
- OpenSIPS with MI HTTP module enabled

### Installation Steps
1. Extract files to web directory
2. Configure `config/db.inc.php`
3. Import database schema: `mysql < config/db_schema.mysql`
4. Configure Apache (see INSTALL file)
5. Set file permissions
6. Configure OpenSIPS MI HTTP module
7. Set up cron jobs
8. Access via web browser

## Key Design Decisions

1. **Session-based caching**: Boxes, configs, and systems cached in session for performance
2. **Multi-database support**: Each tool can use different database connections
3. **Modular architecture**: Tools are independent modules with standard interfaces
4. **Frame-based UI**: Uses HTML frames for menu/content separation (legacy design)
5. **JSON-RPC for MI**: Standard protocol for OpenSIPS communication
6. **Widget-based dashboard**: Extensible dashboard system

## Maintenance Notes

- Statistics collection requires cron job setup
- Session cache may need clearing after config changes
- Box configurations cached in session - may need logout/login to refresh
- Database connections pooled via PDO
- Error logging via PHP error_log
