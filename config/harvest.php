<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Deployment Environments
    |--------------------------------------------------------------------------
    |
    | Define your deployment environments and the actions to perform.
    | Each environment can have its own SSH connection and deployment steps.
    |
    | Supported Configuration Options:
    |
    | - ssh_command (string, required)
    |   The SSH command used to connect to the server.
    |   Example: 'ssh user@host -p2121' or 'ssh my-server-alias'
    |
    | - ask_confirmation (bool, optional, default: false)
    |   Whether to prompt for confirmation before executing deployment actions.
    |   Set to true for production environments.
    |
    | - actions (array, required)
    |   List of shell commands to execute on the remote server.
    |   Commands are executed sequentially. Deployment stops on first failure.
    |
    | Usage: php artisan harvest:deploy <environment>
    |
    | Example Configuration:
    |
    | 'production' => [
    |     'ssh_command' => 'ssh deployer@prod.example.com',
    |     'ask_confirmation' => true,
    |     'actions' => [
    |         'cd /var/www/my-app',
    |         'git pull origin main',
    |         'composer install --no-dev --optimize-autoloader',
    |         'php artisan migrate --force',
    |         'php artisan config:cache',
    |         'php artisan queue:restart',
    |     ],
    | ],
    |
    */

    'deployments' => [
        // Add your deployment environments here
    ],
];
