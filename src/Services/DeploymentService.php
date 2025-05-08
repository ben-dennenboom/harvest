<?php

namespace Dennenboom\Harvest\Services;

use Dennenboom\Harvest\Utils\FileSystem;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

class DeploymentService
{
    /**
     * The application configuration.
     *
     * @var array
     */
    protected $appConfig;

    /**
     * The global configuration.
     *
     * @var array
     */
    protected $config;

    /**
     * The SymfonyStyle instance.
     *
     * @var \Symfony\Component\Console\Style\SymfonyStyle
     */
    protected $io;

    /**
     * The application path.
     *
     * @var string
     */
    protected $appPath;

    /**
     * The releases path.
     *
     * @var string
     */
    protected $releasesPath;

    /**
     * The shared path.
     *
     * @var string
     */
    protected $sharedPath;

    /**
     * The current path.
     *
     * @var string
     */
    protected $currentPath;

    /**
     * The new release path.
     *
     * @var string
     */
    protected $newReleasePath;

    /**
     * The new release name.
     *
     * @var string
     */
    protected $newReleaseName;

    /**
     * Create a new DeploymentService instance.
     *
     * @param array $appConfig
     * @param array $config
     * @param \Symfony\Component\Console\Style\SymfonyStyle $io
     */
    public function __construct(array $appConfig, array $config, SymfonyStyle $io)
    {
        $this->appConfig = $appConfig;
        $this->config = $config;
        $this->io = $io;

        $this->appPath = $appConfig['path'];
        $this->releasesPath = "{$this->appPath}/releases";
        $this->sharedPath = "{$this->appPath}/shared";
        $this->currentPath = "{$this->appPath}/current";
    }

    /**
     * Deploy the application.
     *
     * @param array $options
     *
     * @return bool
     * @throws \Exception
     */
    public function deploy(array $options = []): bool
    {
        try {
            $this->io->section('Starting deployment process');

            $this->prepareDeployment();
            $this->cloneRepository();
            $this->setupSharedFiles();
            $this->runComposerInstall();
            $this->runNpmBuild();

            if (!($options['skip_tests'] ?? false)) {
                $this->runTests($options['force'] ?? false);
            }

            if (!($options['skip_migrations'] ?? false)) {
                $this->runMigrations();
            }

            $this->optimizeApplication();
            $this->executeCustomScripts();
            $this->runHooks('before_deploy');
            $this->activateRelease();
            $this->runHooks('after_deploy');
            $this->cleanup();

            return true;
        } catch (\Exception $e) {
            $this->io->error($e->getMessage());
            $this->cleanupFailedDeployment();
            throw $e;
        }
    }

    /**
     * Prepare the deployment.
     *
     * @return void
     */
    protected function prepareDeployment(): void
    {
        $this->io->text('Preparing deployment directory structure');

        FileSystem::ensureDirectoryExists($this->releasesPath);
        FileSystem::ensureDirectoryExists($this->sharedPath);

        foreach ($this->appConfig['shared_dirs'] ?? ['storage'] as $dir) {
            FileSystem::ensureDirectoryExists("{$this->sharedPath}/{$dir}");
        }

        $this->newReleaseName = date('YmdHis');
        $this->newReleasePath = "{$this->releasesPath}/{$this->newReleaseName}";
    }

    /**
     * Clone the repository.
     *
     * @return void
     */
    protected function cloneRepository(): void
    {
        $this->io->text("Cloning repository from {$this->appConfig['repository']}");

        $command = [
            'git',
            'clone',
            '-b',
            $this->appConfig['branch'],
            '--depth',
            '1',
            $this->appConfig['repository'],
            $this->newReleasePath,
        ];

        $this->runProcess($command);
    }

    /**
     * Run a process.
     *
     * @param array $command
     *
     * @return void
     */
    protected function runProcess(array $command): void
    {
        $process = new Process($command);
        $process->setTimeout(3600); // 1 hour timeout
        $process->run();

        if (!$process->isSuccessful()) {
            throw new ProcessFailedException($process);
        }
    }

    /**
     * Set up shared files and directories.
     *
     * @return void
     */
    protected function setupSharedFiles(): void
    {
        $this->io->text('Setting up shared files and directories');

        foreach ($this->appConfig['shared_dirs'] ?? ['storage'] as $dir) {
            $path = "{$this->newReleasePath}/{$dir}";

            if (is_dir($path)) {
                FileSystem::removeDirectory($path);
            } elseif (is_link($path)) {
                unlink($path);
            }

            FileSystem::ensureDirectoryExists("{$this->sharedPath}/{$dir}");
            symlink("{$this->sharedPath}/{$dir}", $path);
        }

        foreach ($this->appConfig['shared_files'] ?? ['.env'] as $file) {
            $releasePath = "{$this->newReleasePath}/{$file}";
            $sharedPath = "{$this->sharedPath}/{$file}";

            if (!file_exists($sharedPath) && file_exists("{$releasePath}.example")) {
                copy("{$releasePath}.example", $sharedPath);
            }

            if (file_exists($releasePath)) {
                unlink($releasePath);
            }

            symlink($sharedPath, $releasePath);
        }
    }

    /**
     * Run composer install.
     *
     * @return void
     */
    protected function runComposerInstall(): void
    {
        $this->io->text('Running composer install');

        $command = [
            $this->config['composer_binary'],
            'install',
            '--no-dev',
            '--prefer-dist',
            '--optimize-autoloader',
            '--no-interaction',
            '--working-dir=' . $this->newReleasePath,
        ];

        $this->runProcess($command);
    }

    /**
     * Run NPM build.
     *
     * @return void
     */
    protected function runNpmBuild(): void
    {
        if (!file_exists("{$this->newReleasePath}/package.json")) {
            $this->io->text('No package.json found, skipping npm build');

            return;
        }

        $this->io->text('Running npm install and build');

        $command = [
            $this->config['npm_binary'],
            'ci',
            '--prefix=' . $this->newReleasePath,
        ];

        $this->runProcess($command);

        $command = [
            $this->config['npm_binary'],
            'run',
            'build',
            '--prefix=' . $this->newReleasePath,
        ];

        $this->runProcess($command);
    }

    /**
     * Run tests.
     *
     * @param bool $force
     *
     * @return void
     * @throws \Exception
     */
    protected function runTests(bool $force = false): void
    {
        $this->io->text('Running tests');

        $command = [
            $this->config['php_binary'],
            "{$this->newReleasePath}/artisan",
            'test',
        ];

        try {
            $this->runProcess($command);
        } catch (\Exception $e) {
            if (!$force) {
                throw new \Exception('Tests failed. Deployment aborted.');
            }

            $this->io->warning('Tests failed, but continuing deployment due to --force option');
        }
    }

    /**
     * Run migrations.
     *
     * @return void
     */
    protected function runMigrations(): void
    {
        $this->io->text('Running database migrations');

        $command = [
            $this->config['php_binary'],
            "{$this->newReleasePath}/artisan",
            'migrate',
            '--force',
        ];

        $this->runProcess($command);
    }

    /**
     * Optimize the application.
     *
     * @return void
     */
    protected function optimizeApplication(): void
    {
        $this->io->text('Optimizing application');

        $this->runArtisanCommand('view:clear');
        $this->runArtisanCommand('cache:clear');

        $this->runArtisanCommand('config:cache');
        $this->runArtisanCommand('route:cache');
        $this->runArtisanCommand('view:cache');
    }

    /**
     * Run an Artisan command.
     *
     * @param string $command
     * @param array $parameters
     *
     * @return void
     */
    protected function runArtisanCommand(string $command, array $parameters = []): void
    {
        $artisanCommand = [
            $this->config['php_binary'],
            "{$this->newReleasePath}/artisan",
            $command,
        ];

        $artisanCommand = array_merge($artisanCommand, $parameters);
        $this->runProcess($artisanCommand);
    }

    /**
     * Execute custom scripts.
     *
     * @return void
     */
    protected function executeCustomScripts(): void
    {
        $scriptsDir = "{$this->newReleasePath}/deploy-scripts";

        if (!is_dir($scriptsDir)) {
            return;
        }

        $this->io->text('Executing custom deployment scripts');

        $executedDir = "{$scriptsDir}/executed";
        FileSystem::ensureDirectoryExists($executedDir);

        foreach (glob("{$scriptsDir}/*.php") as $script) {
            if (is_file($script) && basename($script) !== '.' && basename($script) !== '..') {
                $scriptName = basename($script);

                $this->io->text("- Executing {$scriptName}");

                $command = [
                    $this->config['php_binary'],
                    $script,
                ];

                $this->runProcess($command);

                rename($script, "{$executedDir}/{$scriptName}");
            }
        }
    }

    /**
     * Run deployment hooks.
     *
     * @param string $hook
     *
     * @return void
     */
    protected function runHooks(string $hook): void
    {
        if (!isset($this->appConfig['hooks'][$hook]) || empty($this->appConfig['hooks'][$hook])) {
            return;
        }

        $this->io->text("Running {$hook} hooks");

        foreach ($this->appConfig['hooks'][$hook] as $command) {
            $this->io->text("- {$command}");

            $process = Process::fromShellCommandline($command, $this->newReleasePath);
            $process->setTimeout(3600);
            $process->run();

            if (!$process->isSuccessful()) {
                throw new ProcessFailedException($process);
            }
        }
    }

    /**
     * Activate the new release.
     *
     * @return void
     */
    protected function activateRelease(): void
    {
        $this->io->text('Activating new release');

        $tempLink = "{$this->appPath}/release-temp";

        if (file_exists($tempLink)) {
            unlink($tempLink);
        }

        symlink($this->newReleasePath, $tempLink);

        rename($tempLink, $this->currentPath);
    }

    /**
     * Clean up old releases.
     *
     * @return void
     */
    protected function cleanup(): void
    {
        $this->io->text('Cleaning up old releases');

        $releases = $this->listReleases();
        $keepReleases = $this->appConfig['releases_to_keep'] ?? 5;

        if (count($releases) > $keepReleases) {
            for ($i = $keepReleases; $i < count($releases); $i++) {
                $releasePath = "{$this->releasesPath}/{$releases[$i]['name']}";

                $this->io->text("- Removing {$releases[$i]['name']}");
                FileSystem::removeDirectory($releasePath);
            }
        }
    }

    /**
     * List all releases.
     *
     * @return array
     */
    public function listReleases(): array
    {
        $releases = [];

        if (!is_dir($this->releasesPath)) {
            return [];
        }

        foreach (glob("{$this->releasesPath}/*", GLOB_ONLYDIR) as $releaseDir) {
            $releaseName = basename($releaseDir);

            $timestamp = \DateTime::createFromFormat('YmdHis', $releaseName)->getTimestamp();

            $releases[] = [
                'name'      => $releaseName,
                'path'      => $releaseDir,
                'timestamp' => $timestamp,
            ];
        }

        usort($releases, function ($a, $b) {
            return $b['timestamp'] <=> $a['timestamp'];
        });

        return $releases;
    }

    /**
     * Clean up a failed deployment.
     *
     * @return void
     */
    protected function cleanupFailedDeployment(): void
    {
        $this->io->text('Cleaning up failed deployment');

        if (is_dir($this->newReleasePath)) {
            FileSystem::removeDirectory($this->newReleasePath);
        }
    }

    /**
     * Get the current release.
     *
     * @return string|null
     */
    public function getCurrentRelease(): ?string
    {
        if (!file_exists($this->currentPath) || !is_link($this->currentPath)) {
            return null;
        }

        $target = readlink($this->currentPath);

        return basename($target);
    }
}
