<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Application Configuration
    |--------------------------------------------------------------------------
    |
    | Configure your Laravel applications for deployment with Harvest.
    |
    */

    'applications' => [
        'default' => [
            'name' => 'my-app',                             // Application name
            'repository' => 'git@github.com:org/repo.git',  // Git repository URL
            'branch' => 'main',                             // Default branch to deploy
            'path' => '/var/www/my-app',                    // Deployment path
            'releases_to_keep' => 5,                        // Number of releases to keep
            'shared_dirs' => [                              // Directories to share between releases
                'storage',
                'storage/app',
                'storage/framework',
                'storage/logs',
            ],
            'shared_files' => [                             // Files to share between releases
                '.env',
            ],
            'writable_dirs' => [                            // Directories that should be writable
                'bootstrap/cache',
                'storage',
            ],
            'hooks' => [                                    // Custom hooks to run during deployment
                'before_deploy' => [
                    // Commands to run before deployment
                ],
                'after_deploy' => [
                    // Commands to run after deployment
                ],
            ],
        ],

        // You can define multiple applications
        // 'another-app' => [
        //     'name' => 'another-app',
        //     'repository' => 'git@github.com:org/another-repo.git',
        //     'branch' => 'develop',
        //     'path' => '/var/www/another-app',
        // ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Default Application
    |--------------------------------------------------------------------------
    |
    | The default application to deploy if none is specified.
    |
    */

    'default_app' => 'default',

    /*
    |--------------------------------------------------------------------------
    | Global Configuration
    |--------------------------------------------------------------------------
    |
    | Global configuration options for Harvest.
    |
    */

    'php_binary' => 'php',            // PHP binary path
    'composer_binary' => 'composer',  // Composer binary path
    'npm_binary' => 'npm',            // NPM binary path

    /*
    |--------------------------------------------------------------------------
    | Deployment Settings
    |--------------------------------------------------------------------------
    |
    | General deployment settings.
    |
    */

    'run_tests' => true,              // Whether to run tests before deployment
    'run_migrations' => true,         // Whether to run migrations during deployment
];
