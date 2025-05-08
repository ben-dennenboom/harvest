<?php

namespace Dennenboom\Harvest;

use Dennenboom\Harvest\Commands\DeployCommand;
use Dennenboom\Harvest\Commands\ReleasesCommand;
use Dennenboom\Harvest\Commands\RollbackCommand;
use Symfony\Component\Console\Application;

class HarvestManager
{
    /**
     * The application version.
     *
     * @var string
     */
    const VERSION = '1.0.0';

    /**
     * The Symfony Console application instance.
     *
     * @var \Symfony\Component\Console\Application
     */
    protected $app;

    /**
     * The configuration data.
     *
     * @var array
     */
    protected $config;

    /**
     * Create a new HarvestManager instance.
     */
    public function __construct()
    {
        $this->app = new Application('Harvest', self::VERSION);
        $this->loadConfig();
        $this->registerCommands();
    }

    /**
     * Run the Harvest CLI application.
     *
     * @return int
     */
    public function run(): int
    {
        return $this->app->run();
    }

    /**
     * Load the configuration.
     *
     * @return void
     */
    protected function loadConfig(): void
    {
        $configFile = $this->findConfigFile();

        if ($configFile) {
            $this->config = require $configFile;
        } else {
            // Use default config if no config file is found
            $this->config = [
                'applications' => [],
                'default_app' => 'default',
                'php_binary' => 'php',
                'composer_binary' => 'composer',
                'npm_binary' => 'npm',
                'run_tests' => true,
                'run_migrations' => true,
            ];
        }
    }

    /**
     * Find the config file.
     *
     * @return string|null
     */
    protected function findConfigFile(): ?string
    {
        $possibleLocations = [
            getcwd() . '/harvest.php',                // Current directory
            getcwd() . '/config/harvest.php',         // Config directory
            $_SERVER['HOME'] . '/.harvest/config.php' // User's home directory
        ];

        foreach ($possibleLocations as $location) {
            if (file_exists($location)) {
                return $location;
            }
        }

        return null;
    }

    /**
     * Register the commands.
     *
     * @return void
     */
    protected function registerCommands(): void
    {
        $this->app->add(new DeployCommand($this->config));
        $this->app->add(new RollbackCommand($this->config));
        $this->app->add(new ReleasesCommand($this->config));
    }

    /**
     * Get the configuration.
     *
     * @return array
     */
    public function getConfig(): array
    {
        return $this->config;
    }
}
