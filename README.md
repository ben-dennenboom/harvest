# Harvest

A simple deployment automation tool for Laravel applications, inspired by Laravel Envoy. Harvest helps you automate deployment scenarios by executing a series of commands on remote servers via SSH.

## Features

- Simple deployment automation
- SSH-based remote execution
- Optional confirmation prompts
- Easy configuration
- Docker-friendly

## Installation

Install via Composer:

```bash
composer require dennenboom/harvest
```

The package will auto-register itself via Laravel's package discovery.

## Configuration

Publish the configuration file:

```bash
php artisan vendor:publish --tag=harvest-config
```

This will create a `config/harvest.php` file. Configure your deployment environments:

```php
return [
    'deployments' => [
        'uat' => [
            // The SSH command used to connect to the server
            'ssh_command' => 'ssh user@host -p2121',

            // Prompt for confirmation before executing actions
            'ask_confirmation' => true,

            // Actions to execute on the remote server
            'actions' => [
                'cd /var/www/my-app',
                'git pull origin main',
                'composer install --no-dev --optimize-autoloader',
                'php artisan migrate --force',
                'php artisan config:cache',
            ],
        ],

        'production' => [
            'ssh_command' => 'ssh user@production-host',
            'ask_confirmation' => true,
            'actions' => [
                'cd /var/www/my-app',
                'git pull origin main',
                'composer install --no-dev --optimize-autoloader',
                'php artisan migrate --force',
            ],
        ],
    ],
];
```

## Usage

Deploy to a specific environment:

```bash
php artisan harvest:deploy uat
```

Skip confirmation prompt:

```bash
php artisan harvest:deploy uat --no-confirm
```

## SSH Authentication

Harvest executes SSH commands directly, so authentication is handled by your SSH configuration. This means:

- Your SSH keys should be set up and configured outside of Harvest
- Use `ssh-agent` to manage your keys
- Configure SSH aliases in your `~/.ssh/config` for easier management

Example SSH config (`~/.ssh/config`):

```
Host uat-server
    HostName uat.example.com
    User deployer
    Port 2121
    IdentityFile ~/.ssh/deploy_key
```

Then in your Harvest config:

```php
'uat' => [
    'ssh_command' => 'ssh uat-server',
    // ...
],
```

## Docker Considerations

If your Laravel application runs inside Docker containers, you have several options for SSH key management:

### Option 1: SSH Agent Forwarding (Recommended)

Mount your SSH agent socket into the container:

```yaml
# docker-compose.yml
services:
  app:
    volumes:
      - $SSH_AUTH_SOCK:/ssh-agent
    environment:
      - SSH_AUTH_SOCK=/ssh-agent
```

Then run:

```bash
docker-compose exec app php artisan harvest:deploy uat
```

### Option 2: Mount SSH Keys as Volume

Mount your SSH directory (ensure proper permissions):

```yaml
# docker-compose.yml
services:
  app:
    volumes:
      - ~/.ssh:/root/.ssh:ro
```

**Warning**: Be careful with this approach and never commit SSH keys to your repository.

### Option 3: Run Harvest Outside Docker

Since Harvest only orchestrates SSH commands, you can run it from your host machine:

```bash
# On your host machine
php artisan harvest:deploy uat
```

### Option 4: Use Environment Variables for SSH Config

You can set SSH connection details via environment variables:

```bash
# .env (DO NOT COMMIT)
HARVEST_UAT_SSH=ssh user@host -p2121
```

Then in your config:

```php
'uat' => [
    'ssh_command' => env('HARVEST_UAT_SSH', 'ssh user@host'),
    // ...
],
```

## How It Works

1. Harvest reads your deployment configuration from `config/harvest.php`
2. It connects to the remote server using the configured SSH command
3. Each action is executed sequentially on the remote server
4. If any action fails, deployment stops immediately
5. Success/failure status is reported back to you

## Configuration Options

### Per-Environment Settings

- `ssh_command` (required): The SSH command to connect to the server
- `ask_confirmation` (optional, default: `false`): Whether to prompt for confirmation before deployment
- `actions` (required): Array of commands to execute on the remote server

## Examples

### Simple Deployment

```php
'dev' => [
    'ssh_command' => 'ssh deployer@dev.example.com',
    'ask_confirmation' => false,
    'actions' => [
        'cd /var/www/app',
        'git pull',
        'composer install',
    ],
],
```

### Production with Confirmation

```php
'production' => [
    'ssh_command' => 'ssh deployer@prod.example.com',
    'ask_confirmation' => true,
    'actions' => [
        'cd /var/www/app',
        'git pull origin main',
        'composer install --no-dev --optimize-autoloader',
        'php artisan down',
        'php artisan migrate --force',
        'php artisan config:cache',
        'php artisan route:cache',
        'php artisan view:cache',
        'php artisan queue:restart',
        'php artisan up',
    ],
],
```

### Multiple Servers

```php
'staging' => [
    'ssh_command' => 'ssh deployer@staging.example.com',
    'actions' => ['cd /var/www/app', 'git pull'],
],
'production-web' => [
    'ssh_command' => 'ssh deployer@web1.example.com',
    'actions' => ['cd /var/www/app', 'git pull'],
],
'production-worker' => [
    'ssh_command' => 'ssh deployer@worker1.example.com',
    'actions' => ['cd /var/www/app', 'git pull', 'supervisorctl restart all'],
],
```

## License

MIT License. See [LICENSE](LICENSE) for details.
