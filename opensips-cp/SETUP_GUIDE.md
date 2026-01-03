# OpenSIPS Control Panel - Setup and Run Guide

## Prerequisites

### System Requirements
- **Web Server**: Apache (recommended) or Nginx
- **PHP**: Version 5.6 or higher with required extensions
- **Database**: MySQL/MariaDB, PostgreSQL, SQLite, or Oracle
- **OpenSIPS**: OpenSIPS SIP server with MI HTTP module enabled (for full functionality)

### Required PHP Extensions
- `php-pdo` - Database access
- `php-curl` - HTTP requests to OpenSIPS MI
- `php-gd` - Image processing for charts
- `php-mysql` or `php-pgsql` - Database driver
- `php-json` - JSON handling (usually built-in)

## Installation Steps

### 1. Install Dependencies

#### On macOS (using Homebrew):
```bash
# Install Apache
brew install httpd

# Install PHP
brew install php

# Install MySQL (if using MySQL)
brew install mysql

# Install PHP extensions
brew install php-gd
pecl install apcu  # Optional: for caching
```

#### On Debian/Ubuntu:
```bash
sudo apt-get update
sudo apt-get install apache2 libapache2-mod-php php php-gd php-mysql php-curl php-cli php-pear
```

#### On RedHat/CentOS:
```bash
sudo yum install httpd php php-gd php-mysql php-curl php-cli php-pear
```

### 2. Database Setup

#### Create Database and User
```bash
# For MySQL
mysql -u root -p
CREATE DATABASE opensips;
CREATE USER 'opensips'@'localhost' IDENTIFIED BY 'opensipsrw';
GRANT ALL PRIVILEGES ON opensips.* TO 'opensips'@'localhost';
FLUSH PRIVILEGES;
EXIT;
```

#### Import OCP Schema
```bash
cd /Users/bhaveshvarma/Pictures/CROWN_SOLUTIONS/ops/opensips-cp
mysql -u opensips -p opensips < config/db_schema.mysql
```

**Note**: This creates the OCP tables and adds a default admin user:
- **Username**: `admin`
- **Password**: `opensips`

### 3. Configure Database Connection

Edit the database configuration file:
```bash
nano config/db.inc.php
```

Update with your database credentials:
```php
$config->db_driver = "mysql";
$config->db_host = "localhost";
$config->db_port = "";
$config->db_user = "opensips";
$config->db_pass = "opensipsrw";
$config->db_name = "opensips";
```

### 4. Web Server Configuration

#### Option A: Using Apache (Recommended)

Create Apache virtual host configuration:

**For macOS** (`/usr/local/etc/httpd/extra/httpd-vhosts.conf` or create new file):
```apache
<VirtualHost *:80>
    ServerName opensips-cp.local
    DocumentRoot "/Users/bhaveshvarma/Pictures/CROWN_SOLUTIONS/ops/opensips-cp/web"
    
    <Directory "/Users/bhaveshvarma/Pictures/CROWN_SOLUTIONS/ops/opensips-cp/web">
        Options Indexes FollowSymLinks MultiViews
        AllowOverride All
        Require all granted
    </Directory>
    
    <Directory "/Users/bhaveshvarma/Pictures/CROWN_SOLUTIONS/ops/opensips-cp">
        Options Indexes FollowSymLinks MultiViews
        AllowOverride None
        Require all denied
    </Directory>
    
    Alias /cp "/Users/bhaveshvarma/Pictures/CROWN_SOLUTIONS/ops/opensips-cp/web"
    
    <DirectoryMatch "/Users/bhaveshvarma/Pictures/CROWN_SOLUTIONS/ops/opensips-cp/web/tools/.*/.*/(template|custom_actions|lib)/">
        Require all denied
    </DirectoryMatch>
    
    ErrorLog "/usr/local/var/log/httpd/opensips-cp_error.log"
    CustomLog "/usr/local/var/log/httpd/opensips-cp_access.log" common
</VirtualHost>
```

**For Linux** (`/etc/apache2/sites-available/opensips-cp.conf`):
```apache
<VirtualHost *:80>
    ServerName opensips-cp.local
    DocumentRoot "/var/www/html/opensips-cp/web"
    
    <Directory "/var/www/html/opensips-cp/web">
        Options Indexes FollowSymLinks MultiViews
        AllowOverride None
        Require all granted
    </Directory>
    
    <Directory "/var/www/html/opensips-cp">
        Options Indexes FollowSymLinks MultiViews
        AllowOverride None
        Require all denied
    </Directory>
    
    Alias /cp "/var/www/html/opensips-cp/web"
    
    <DirectoryMatch "/var/www/html/opensips-cp/web/tools/.*/.*/(template|custom_actions|lib)/">
        Require all denied
    </DirectoryMatch>
</VirtualHost>
```

**Enable the site (Linux only)**:
```bash
sudo a2ensite opensips-cp.conf
sudo systemctl restart apache2
```

**For macOS**, add to `/usr/local/etc/httpd/httpd.conf`:
```apache
Include /usr/local/etc/httpd/extra/httpd-vhosts.conf
```

#### Option B: Using PHP Built-in Server (Development Only)

For quick testing without Apache setup:
```bash
cd /Users/bhaveshvarma/Pictures/CROWN_SOLUTIONS/ops/opensips-cp/web
php -S localhost:8000
```

Then access: `http://localhost:8000`

**Note**: Built-in server is for development only. Use Apache for production.

### 5. Set File Permissions

```bash
# For macOS/Linux
cd /Users/bhaveshvarma/Pictures/CROWN_SOLUTIONS/ops/opensips-cp

# Set ownership (adjust user/group for your system)
# macOS: use your username
chown -R $(whoami):staff .

# Linux: use www-data
# sudo chown -R www-data:www-data /var/www/html/opensips-cp/

# Set permissions
chmod -R 755 .
chmod -R 775 config/  # If needed for write access
```

### 6. Configure OpenSIPS (Optional but Recommended)

To use full functionality, configure OpenSIPS to enable MI HTTP:

Edit your `opensips.cfg` file and add before routing logic:
```
#### HTTPD module
loadmodule "httpd.so"
modparam("httpd", "ip", "127.0.0.1")
modparam("httpd", "port", 8888)

#### MI HTTP module
loadmodule "mi_http.so"
```

Reload OpenSIPS:
```bash
opensipsctl reload
```

### 7. Configure OpenSIPS Box (Optional)

If you have OpenSIPS running, configure a box in OCP:
1. Login to OCP
2. Go to Admin → Boxes Config
3. Add a new box with MI connection: `json:127.0.0.1:8888/mi`

### 8. Set Up Cron Jobs (Optional)

For statistics monitoring, set up cron job:

```bash
# Edit the cron file first to set correct path
nano config/tools/system/smonitor/opensips_stats_cron

# Copy to cron.d (Linux)
sudo cp config/tools/system/smonitor/opensips_stats_cron /etc/cron.d/

# Or add to crontab (macOS/Linux)
crontab -e
# Add: */1 * * * * /path/to/opensips-cp/cron_job/get_opensips_stats.php
```

## Running the Application

### Start Services

#### 1. Start Database
```bash
# MySQL (macOS)
brew services start mysql

# MySQL (Linux)
sudo systemctl start mysql
```

#### 2. Start Web Server

**Apache (macOS)**:
```bash
brew services start httpd
# Or manually:
sudo apachectl start
```

**Apache (Linux)**:
```bash
sudo systemctl start apache2
```

**PHP Built-in Server**:
```bash
cd /Users/bhaveshvarma/Pictures/CROWN_SOLUTIONS/ops/opensips-cp/web
php -S localhost:8000
```

### Access the Application

1. **Open browser** and navigate to:
   - With Apache: `http://localhost/cp` or `http://opensips-cp.local/cp`
   - With PHP server: `http://localhost:8000`

2. **Login** with default credentials:
   - Username: `admin`
   - Password: `opensips`

3. **Change password** after first login (recommended)

## Testing the CSV Upload Feature

### 1. Navigate to Dynamic Routing
- Login to OCP
- Go to **System** → **Dynamic Routing** → **Gateways** tab

### 2. Create Test CSV File

Create a file `test_gateways.csv`:
```csv
gwid,type,address,strip,pri_prefix,probe_mode,socket,state,description
gateway1,1,192.168.1.1:5060,0,,0,,0,Test Gateway 1
gateway2,1,192.168.1.2:5060,0,,1,,0,Test Gateway 2
gateway3,1,192.168.1.3:5060,0,,2,,0,Test Gateway 3
```

### 3. Upload CSV
- Click **"Upload CSV"** button
- Select your CSV file
- Click **"Upload"**
- You should see success message with count of imported gateways

### 4. Verify
- Check that gateways appear in the list
- Try uploading the same file again to see duplicate detection (should skip)

## Troubleshooting

### Common Issues

#### 1. Database Connection Error
- Check `config/db.inc.php` credentials
- Verify database is running: `mysql -u opensips -p`
- Check database exists: `SHOW DATABASES;`

#### 2. Permission Denied
- Check file permissions: `ls -la`
- Ensure web server user has read access
- Check Apache error logs

#### 3. PHP Errors
- Check PHP version: `php -v` (needs 5.6+)
- Check PHP extensions: `php -m | grep -E 'pdo|curl|gd|mysql'`
- Check PHP error logs

#### 4. 404 Not Found
- Verify Apache DocumentRoot points to `web/` directory
- Check Alias `/cp` is configured
- Verify mod_rewrite is enabled (if using)

#### 5. Session Errors
- Check PHP session directory is writable
- Verify `session.save_path` in `php.ini`

### View Logs

**Apache Error Log**:
```bash
# macOS
tail -f /usr/local/var/log/httpd/error_log

# Linux
tail -f /var/log/apache2/error.log
```

**PHP Error Log**:
```bash
tail -f /var/log/php_errors.log
# Or check php.ini for error_log location
```

**OCP Access Log**:
```bash
tail -f config/access.log
```

## Development Mode

For development, you can:

1. **Enable error display** in PHP:
   - Edit `php.ini` or create `.htaccess` in `web/`:
   ```apache
   php_flag display_errors on
   php_value error_reporting E_ALL
   ```

2. **Use PHP built-in server**:
   ```bash
   cd web
   php -S localhost:8000
   ```

3. **Enable debug mode** (if available in config)

## Production Checklist

- [ ] Change default admin password
- [ ] Configure proper file permissions
- [ ] Set up SSL/HTTPS
- [ ] Configure firewall rules
- [ ] Set up regular database backups
- [ ] Configure log rotation
- [ ] Disable PHP error display
- [ ] Set up monitoring
- [ ] Configure cron jobs for statistics

## Next Steps

1. **Configure OpenSIPS boxes** in Admin → Boxes Config
2. **Set up database connections** for tools in Admin → DB Config
3. **Configure tool settings** as needed
4. **Add additional admin users** in Admin → Access
5. **Set up statistics monitoring** (requires cron jobs)

## Support

- Official Documentation: http://controlpanel.opensips.org/
- OpenSIPS Project: https://www.opensips.org/
