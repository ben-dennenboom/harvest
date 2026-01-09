<?php

namespace Dennenboom\Harvest\Commands;

use Illuminate\Console\Command;
use Symfony\Component\Process\Process;

class DeployCommand extends Command
{
    protected $signature = 'harvest:deploy {environment : The deployment environment to use}
                            {--no-confirm : Skip confirmation prompt}
                            {--var=* : Pass variables as key=value pairs}';

    protected $description = 'Deploy your application to the specified environment';

    protected ?string $sudoPassword = null;

    protected array $variables = [];

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

        $this->collectVariables($deployment);

        $this->promptForSudoPasswordIfNeeded($deployment);

        $askConfirmation = $deployment['ask_confirmation'] ?? false;

        if ($askConfirmation && !$this->option('no-confirm')) {
            $this->info("You are about to deploy to: {$environment}");
            $this->line("SSH Command: {$deployment['ssh_command']}");

            if (!empty($this->variables)) {
                $this->line("Variables:");
                foreach ($this->variables as $key => $value) {
                    $this->line("  {$key}: {$value}");
                }
            }

            $this->line("Actions to execute:");
            $processedActions = $this->processActions($deployment['actions']);
            foreach ($processedActions as $index => $action) {
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

    protected function collectVariables(array $deployment): void
    {
        foreach ($this->option('var') as $varString) {
            if (strpos($varString, '=') !== false) {
                [$key, $value] = explode('=', $varString, 2);
                $this->variables[$key] = $value;
            }
        }

        $requiredVars = $deployment['variables'] ?? [];

        foreach ($requiredVars as $varName => $varConfig) {
            if (isset($this->variables[$varName])) {
                continue;
            }

            if (is_string($varConfig)) {
                $this->variables[$varName] = $this->ask($varConfig);
            } elseif (is_array($varConfig)) {
                $prompt = $varConfig['prompt'] ?? "Enter value for {$varName}";
                $default = $varConfig['default'] ?? null;

                $this->variables[$varName] = $this->ask($prompt, $default);
            }
        }
    }

    protected function promptForSudoPasswordIfNeeded(array $deployment): void
    {
        $needsSudoPassword = $deployment['needs_sudo_password'] ?? false;

        if (!$needsSudoPassword) {
            foreach ($deployment['actions'] as $action) {
                if (str_contains($action, 'sudo -S') || str_contains($action, 'sudo -s')) {
                    $needsSudoPassword = true;
                    break;
                }
            }
        }

        if ($needsSudoPassword) {
            $this->sudoPassword = $this->secret('Enter sudo password (will be hidden)');
        }
    }

    protected function processActions(array $actions): array
    {
        return array_map(function ($action) {
            return $this->replacePlaceholders($action);
        }, $actions);
    }

    protected function replacePlaceholders(string $action): string
    {
        foreach ($this->variables as $key => $value) {
            $action = str_replace("{{$key}}", $value, $action);
        }

        return $action;
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
        $actions = $this->processActions($deployment['actions']);

        foreach ($actions as $index => $action) {
            $this->line("[" . ($index + 1) . "/" . count($actions) . "] {$action}");
        }
        $this->newLine();

        $usesShellSwitch = !empty($actions) && $this->isShellSwitchCommand($actions[0]);

        if ($usesShellSwitch) {
            return $this->executeWithShellSwitch($sshCommand, $actions);
        }

        return $this->executeChainedCommands($sshCommand, $actions);
    }

    protected function isShellSwitchCommand(string $command): bool
    {
        return preg_match('/sudo\s+.*-s\s+(\/bin\/bash|\/bin\/sh|bash|sh)/', $command) === 1;
    }

    protected function executeWithShellSwitch(string $sshCommand, array $actions): int
    {
        $this->line("Detected shell switch command, executing with proper context...");
        $this->newLine();

        $shellSwitchCmd = array_shift($actions);
        $remainingCommands = implode("\n", $actions);

        if ($this->sudoPassword !== null) {
            $escapedPassword = str_replace("'", "'\\''", $this->sudoPassword);

            $fullCommand = sprintf(
                "%s %s",
                $sshCommand,
                escapeshellarg(
                    "{ echo '{$escapedPassword}'; cat << 'HARVEST_EOF'\n{$remainingCommands}\nHARVEST_EOF\n} | {$shellSwitchCmd}"
                )
            );
        } else {
            $fullCommand = sprintf(
                "%s %s",
                $sshCommand,
                escapeshellarg("{$shellSwitchCmd} << 'HARVEST_EOF'\n{$remainingCommands}\nHARVEST_EOF")
            );
        }

        return $this->runProcess($fullCommand);
    }

    protected function runProcess(string $fullCommand): int
    {
        $process = Process::fromShellCommandline($fullCommand);
        $process->setTimeout(null);
        $process->setTty(Process::isTtySupported());

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

    protected function executeChainedCommands(string $sshCommand, array $actions): int
    {
        $this->line("Executing commands...");
        $this->newLine();

        if ($this->sudoPassword !== null) {
            $escapedPassword = str_replace("'", "'\\''", $this->sudoPassword);

            $actions = array_map(function ($action) use ($escapedPassword) {
                if (str_starts_with(trim($action), 'sudo')) {
                    return sprintf("echo '%s' | %s", $escapedPassword, $action);
                }

                return $action;
            }, $actions);
        }

        $chainedCommands = implode(' && ', $actions);
        $fullCommand = sprintf('%s %s', $sshCommand, escapeshellarg($chainedCommands));

        return $this->runProcess($fullCommand);
    }
}
