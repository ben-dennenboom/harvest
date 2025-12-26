<?php

namespace Dennenboom\Harvest\Commands;

use Illuminate\Console\Command;
use Symfony\Component\Process\Process;

class DeployCommand extends Command
{
    protected $signature = 'harvest:deploy {environment : The deployment environment to use}
                            {--no-confirm : Skip confirmation prompt}';

    protected $description = 'Deploy your application to the specified environment';

    public function handle(): int
    {
        $environment = $this->argument('environment');
        $deployments = config('harvest.deployments', []);

        if (!isset($deployments[$environment])) {
            $this->error("Environment '{$environment}' not found in harvest configuration.");
            $this->line('Available environments: ' . implode(', ', array_keys($deployments)));

            return self::FAILURE;
        }

        $deployment = $deployments[$environment];

        if (empty($deployment['ssh_command'])) {
            $this->error("SSH command not configured for environment '{$environment}'.");

            return self::FAILURE;
        }

        if (empty($deployment['actions'])) {
            $this->error("No actions configured for environment '{$environment}'.");

            return self::FAILURE;
        }

        $askConfirmation = $deployment['ask_confirmation'] ?? false;

        if ($askConfirmation && !$this->option('no-confirm')) {
            $this->info("You are about to deploy to: {$environment}");
            $this->line("SSH Command: {$deployment['ssh_command']}");
            $this->line("Actions to execute:");
            foreach ($deployment['actions'] as $index => $action) {
                $this->line("  " . ($index + 1) . ". {$action}");
            }
            $this->newLine();

            if (!$this->confirm('Do you want to continue?', false)) {
                $this->warn('Deployment cancelled.');

                return self::FAILURE;
            }
        }

        $this->info("Deploying to: {$environment}");
        $this->newLine();

        $this->displayConnectionInfo($deployment['ssh_command']);

        return $this->executeDeployment($deployment);
    }

    protected function displayConnectionInfo(string $sshCommand): void
    {
        $whoamiCommand = sprintf('%s %s', $sshCommand, escapeshellarg('whoami && hostname'));
        $process = Process::fromShellCommandline($whoamiCommand);
        $process->setTimeout(10);

        try {
            $process->run();
            if ($process->isSuccessful()) {
                $output = trim($process->getOutput());
                [$user, $hostname] = explode("\n", $output, 2);
                $this->line("Connected as: <info>{$user}@{$hostname}</info>");
                $this->newLine();
            }
        } catch (\Exception $e) {
            // Silently fail if we can't get connection info
        }
    }

    protected function executeDeployment(array $deployment): int
    {
        $sshCommand = $deployment['ssh_command'];
        $actions = $deployment['actions'];

        foreach ($actions as $index => $action) {
            $this->line("[" . ($index + 1) . "/" . count($actions) . "] {$action}");
        }
        $this->newLine();

        $chainedCommands = implode(' && ', $actions);
        $fullCommand = sprintf('%s %s', $sshCommand, escapeshellarg($chainedCommands));

        $process = Process::fromShellCommandline($fullCommand);
        $process->setTimeout(null);
        $process->setTty(Process::isTtySupported());

        $this->line("Executing commands...");
        $this->newLine();

        try {
            $process->run(function ($type, $buffer) {
                echo $buffer;
            });

            if (!$process->isSuccessful()) {
                $this->newLine();
                $this->error("Deployment failed with exit code {$process->getExitCode()}");

                return self::FAILURE;
            }

            $this->newLine();
            $this->info('✓ Deployment completed successfully!');

            return self::SUCCESS;
        } catch (\Exception $e) {
            $this->error("Error executing deployment: {$e->getMessage()}");

            return self::FAILURE;
        }
    }
}
