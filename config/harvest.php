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
    |   Supports variable placeholders: Use {varname} in commands.
    |
    | - variables (array, optional)
    |   Define variables that can be used in actions as placeholders.
    |   Variables can be:
    |     - Simple string prompts: 'version' => 'Enter version to deploy'
    |     - Array with options: 'version' => ['prompt' => '...', 'default' => 'v1.0.0']
    |   Variables can also be passed via command line: --var=version=v1.57.3
    |
    | - needs_sudo_password (bool, optional, default: auto-detect)
    |   Set to true if deployment requires sudo password.
    |   Auto-detected when actions contain 'sudo -S' or 'sudo -s'.
    |   Password will be prompted securely (hidden input).
    |
    | Usage: php artisan harvest:deploy <environment> [options]
    |
    | Options:
    |   --no-confirm    Skip confirmation prompt
    |   --var=key=value Pass variables (can be used multiple times)
    |
    | Example Configuration:
    |
    | 'production' => [
    |     'ssh_command' => 'ssh deployer@prod.example.com',
    |     'ask_confirmation' => true,
    |     'needs_sudo_password' => false,
    |     'variables' => [
    |         'version' => [
    |             'prompt'  => 'Enter the version to deploy',
    |             'default' => 'v1.0.0',
    |         ],
    |     ],
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
