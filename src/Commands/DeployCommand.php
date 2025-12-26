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

        return $this->executeDeployment($deployment);
    }

    protected function executeDeployment(array $deployment): int
    {
        $sshCommand = $deployment['ssh_command'];
        $actions = $deployment['actions'];

        foreach ($actions as $index => $action) {
            $this->line("[" . ($index + 1) . "/" . count($actions) . "] Executing: {$action}");

            $fullCommand = sprintf('%s %s', $sshCommand, escapeshellarg($action));

            $process = Process::fromShellCommandline($fullCommand);
            $process->setTimeout(null); // No timeout
            $process->setTty(Process::isTtySupported()); // Use TTY if supported for interactive commands

            try {
                $process->run(function ($type, $buffer) {
                    echo $buffer;
                });

                if (!$process->isSuccessful()) {
                    $this->error("Command failed with exit code {$process->getExitCode()}");
                    $this->error("Failed command: {$action}");

                    return self::FAILURE;
                }

                $this->line("✓ Success");
                $this->newLine();
            } catch (\Exception $e) {
                $this->error("Error executing command: {$e->getMessage()}");

                return self::FAILURE;
            }
        }

        $this->newLine();
        $this->info('✓ Deployment completed successfully!');

        return self::SUCCESS;
    }
}
