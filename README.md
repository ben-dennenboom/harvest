# Harvest

Harvest is a robust deployment system for Laravel applications that ensures zero downtime, reliable rollbacks, and consistent deployment across multiple servers.

## Requirements

- PHP 8.1+
- Git
- Composer
- npm (for applications with frontend assets)
- Ubuntu server (recommended)
- Laravel 10+ application

## Installation

### Global Installation (Recommended)

Install Harvest globally on your deployment server(s):

```bash
composer global require dennenboom/harvest
```

Ensure Composer's global bin directory is in your PATH:

```bash
# Add this to your .bashrc or .zshrc
export PATH="$PATH:$HOME/.composer/vendor/bin"

# Reload your shell or run:
source ~/.bashrc
```

Verify installation:

```bash
harvest --version
```

### Project-Specific Installation

If you prefer to include Harvest as a project dependency:

```bash
composer require dennenboom/harvest --dev
```

Then use it with:

```bash
vendor/bin/harvest deploy
```

## Quick Start

### 1. Initialize Configuration

Create your configuration directory and copy the template:

```bash
# Create configuration directory
mkdir -p ~/.harvest

# Copy the template (adjust path based on your installation)
cp ~/.composer/vendor/dennenboom/harvest/config/harvest.php ~/.harvest/config.php
```

### 2. Configure Your Applications

Edit the configuration file:

```bash
nano ~/.harvest/config.php
```

Update the configuration with your application details:

```php
<?php

return [
    'applications' => [
        'my-app' => [
            'name' => 'my-app',
            'repository' => 'git@github.com:your-org/your-repo.git',
            'branch' => 'main',
            'path' => '/var/www/my-app',
            'releases_to_keep' => 5,
            // ... other settings
        ],
    ],
    
    'default_app' => 'my-app',
    
    // Global settings...
];
```

### 3. Prepare Your Server

Ensure your deployment directory exists and has proper permissions:

```bash
# Create deployment directory
sudo mkdir -p /var/www/my-app
sudo chown $USER:www-data /var/www/my-app
sudo chmod 755 /var/www/my-app

# Create shared directory for persistent files
mkdir -p /var/www/my-app/shared/storage
```

### 4. Configure Your Web Server

Point your web server to the `current/public` directory:

**Nginx Example:**
```nginx
server {
    listen 80;
    server_name yourdomain.com;
    root /var/www/my-app/current/public;
    
    index index.php;
    
    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }
    
    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.1-fpm.sock;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }
}
```

### 5. Set Up SSH Keys

Ensure your server can access your Git repositories:

```bash
# Generate SSH key if you don't have one
ssh-keygen -t ed25519 -C "your-email@example.com"

# Add the public key to your Git provider (GitHub, GitLab, etc.)
cat ~/.ssh/id_ed25519.pub

# Test SSH access
ssh -T git@github.com
```

### 6. Deploy Your Application

```bash
harvest deploy
```

## Usage

### Deploying

Deploy the default application:
```bash
harvest deploy
```

Deploy a specific application:
```bash
harvest deploy my-app
```

Deploy with options:
```bash
harvest deploy my-app --branch=develop --no-tests --force
```

**Available Options:**
- `--branch=BRANCH` - Deploy a specific branch
- `--no-tests` - Skip running tests
- `--no-migrations` - Skip running migrations
- `--force` - Force deployment even if tests fail

### Listing Releases

```bash
harvest releases [app-name]
```

### Rolling Back

Rollback to the previous release:
```bash
harvest rollback [app-name]
```

Rollback to a specific release:
```bash
harvest rollback my-app --release=20240508123456
```

## One-Time Scripts

Harvest supports executing scripts that should run only once during deployment:

1. Create a `deploy-scripts` directory in your project root
2. Add PHP scripts that you want to run during deployment
3. Scripts are executed once and moved to `deploy-scripts/executed/`

**Example script** (`deploy-scripts/update-permissions.php`):
```php
<?php
// This script will run once during deployment

echo "Updating file permissions...\n";

// Your one-time deployment code here
exec('chmod -R 755 storage/');

echo "Permissions updated successfully.\n";
exit(0); // Return 0 for success
```

## Directory Structure

After deployment, your application directory will look like this:

```
/var/www/my-app/
├── current -> /var/www/my-app/releases/20240508123456  (symlink)
├── releases/
│   ├── 20240508123456/  (latest release)
│   ├── 20240507123456/  (previous release)
│   ├── 20240506123456/
│   ├── 20240505123456/
│   └── 20240504123456/
└── shared/
    ├── .env
    └── storage/
        ├── app/
        ├── framework/
        └── logs/
```

## Troubleshooting

### Permission Issues

```bash
# Fix ownership
sudo chown -R www-data:www-data /var/www/my-app

# Fix storage permissions
sudo chmod -R 775 /var/www/my-app/shared/storage
```

### Git Authentication

```bash
# Test SSH access to your Git provider
ssh -T git@github.com

# If using HTTPS, cache credentials
git config --global credential.helper cache
```

### Config File Not Found

If you get a "config file not found" error:

```bash
# Check if config exists
ls -la ~/.harvest/config.php

# Copy template if missing
cp ~/.composer/vendor/dennenboom/harvest/config/harvest.php ~/.harvest/config.php
```

### Failed Deployment Cleanup

If a deployment fails, Harvest automatically cleans up. However, you can manually clean up if needed:

```bash
# Remove a failed release
rm -rf /var/www/my-app/releases/20240508123456

# Check current symlink
ls -la /var/www/my-app/current
```

## License

The MIT License (MIT). Please see [License File](LICENSE) for more information.