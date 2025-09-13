# WARP.md

This file provides guidance to WARP (warp.dev) when working with code in this repository.

## Project Overview

This is a restaurant management system (resto007) built with PHP 8.3, MariaDB/MySQL, and Bootstrap. The application handles restaurant operations including inventory management, sales, purchases, and reporting.

## Technology Stack

- **PHP**: 8.3.6
- **Database**: MariaDB 10.11.13 / MySQL
- **Web Server**: Apache 2.4.58 
- **Frontend**: Bootstrap 5, Bootstrap Icons
- **Version Control**: Git

## Database Configuration

The application uses two database connection methods:
- **PDO** connection in `config/database.php`
- **MySQLi** connection in `config/koneksi.php`

Database credentials:
- Host: localhost
- Database: resto_db
- Username/Password: Configured in config files
- Timezone: Asia/Makassar

## Project Structure

```
/var/www/html/resto007/
├── config/
│   ├── database.php       # PDO database connection
│   ├── koneksi.php        # MySQLi database connection
│   └── encryption.php     # Password encryption utilities (AES-256-CBC)
├── public/
│   ├── admin/            # Admin panel PHP files
│   ├── css/              # Stylesheets (Bootstrap, custom)
│   ├── js/               # JavaScript files
│   └── images/           # Image assets (gitignored)
├── .git/                 # Git repository
└── .gitignore           # Git ignore file
```

## Key Application Modules

The admin panel (`public/admin/`) includes:
- **Master Data**: Restaurant info, employees, vendors, tables, payment methods
- **Products**: Menu categories, menu items, ingredient categories, ingredients, recipes
- **Purchasing**: Purchase orders, payment processing
- **Sales**: Cashier shifts, promotions, additional costs, COGS, pricing, cancellations
- **Inventory**: Stock management, inventory transactions
- **Reports**: Transaction reports, expense vs sales, quantity analysis
- **Settings**: Application configuration

## User Roles & Permissions

The system supports three user roles:
- **Admin**: Full access to all modules
- **Dapur** (Kitchen): Access to products, purchasing, inventory, and reports
- **Kasir** (Cashier): Access to reports and basic information

## Authentication & Security

- Session-based authentication with role-based access control
- Password encryption using AES-256-CBC with phone number as key
- Encryption utilities in `config/encryption.php`:
  - `encryptPassword()`: Encrypt passwords
  - `decryptPassword()`: Decrypt passwords
  - `verifyPassword()`: Verify password against encrypted version
  - `generateEncryptedPassword()`: Generate new encrypted password

## Common Development Commands

### Database Operations
```bash
# Connect to database
mysql -u samsu -p resto_db

# Backup database
mysqldump -u samsu -p resto_db > backup.sql

# Restore database
mysql -u samsu -p resto_db < backup.sql
```

### PHP Development Server
```bash
# Start PHP development server (if not using Apache)
php -S localhost:8000 -t public/

# Check PHP syntax
php -l public/admin/*.php

# View PHP configuration
php -i | grep -i "configuration file"
```

### Apache Web Server
```bash
# Restart Apache
sudo systemctl restart apache2

# Check Apache status
sudo systemctl status apache2

# View Apache error logs
sudo tail -f /var/log/apache2/error.log

# Enable Apache module
sudo a2enmod rewrite
```

### Git Operations
```bash
# View recent changes
git --no-pager log --oneline -n 10

# Check current status
git status

# View differences
git --no-pager diff

# Create new branch
git checkout -b feature/branch-name

# Push changes
git add .
git commit -m "Description of changes"
git push origin main
```

### File Permissions
```bash
# Set proper permissions for public/images directory
chmod 775 public/images

# Set ownership
sudo chown -R samsu:samsu /var/www/html/resto007
```

## Testing Individual Components

```bash
# Test database connection
php -r "require 'config/database.php'; echo 'PDO connection successful';"
php -r "require 'config/koneksi.php'; echo 'MySQLi connection successful';"

# Test specific admin page (requires login)
curl -I http://localhost/resto007/public/admin/login.php

# Check for PHP errors
php -l public/admin/index.php
```

## Session Management

All admin pages require authentication through `session_start()` and check for:
- `$_SESSION["loggedin"]` - Must be true
- `$_SESSION["nama_lengkap"]` - User's full name
- `$_SESSION["jabatan"]` - User's role (Admin/Dapur/Kasir)
- `$_SESSION["nama_aplikasi"]` - Application name for display

## File Upload Directory

The `/public/images/` directory is used for file uploads and is excluded from version control via `.gitignore`.

## Important Considerations

1. **Database Connection**: The application uses both PDO and MySQLi connections - ensure consistency when creating new features
2. **Timezone**: Set to Asia/Makassar - important for transaction timestamps
3. **Session Security**: All admin pages check session validity - maintain this pattern
4. **Role-Based Access**: Menu items are conditionally displayed based on user role
5. **Password Encryption**: Uses phone number as encryption key - ensure phone numbers are stored securely
6. **File Permissions**: The `public/images` directory requires write permissions for uploads

## Debugging

```bash
# Check PHP error log
sudo tail -f /var/log/apache2/error.log

# Enable PHP error display (development only)
php -d display_errors=1 -d error_reporting=E_ALL public/admin/index.php

# Test database queries
mysql -u samsu -p resto_db -e "SHOW TABLES;"

# Check Apache configuration
apache2ctl configtest
```