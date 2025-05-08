<?php

namespace Dennenboom\Harvest\Commands;

use Dennenboom\Harvest\Services\RollbackService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class RollbackCommand extends Command
{
    /**
     * The configuration data.
     *
     * @var array
     */
    protected $config;

    /**
     * Create a new RollbackCommand instance.
     *
     * @param array $config
     */
    public function __construct(array $config)
    {
        parent::__construct();

        $this->config = $config;
    }

    /**
     * Configure the command.
     *
     * @return void
     */
    protected function configure(): void
    {
        $this
            ->setName('rollback')
            ->setDescription('Rollback to a previous release')
            ->addArgument('app', InputArgument::OPTIONAL, 'The application to rollback')
            ->addOption('release', 'r', InputOption::VALUE_REQUIRED, 'The specific release to rollback to');
    }

    /**
     * Execute the command.
     *
     * @param InputInterface $input
     * @param OutputInterface $output
     *
     * @return int
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Harvest: Zero Downtime Laravel Rollback');

        $appName = $input->getArgument('app') ?? $this->config['default_app'];

        if (!isset($this->config['applications'][$appName])) {
            $io->error("Application '{$appName}' not found in configuration.");

            return Command::FAILURE;
        }

        $appConfig = $this->config['applications'][$appName];
        $specificRelease = $input->getOption('release');

        $io->section("Rolling back application: {$appConfig['name']}");

        try {
            $rollbackService = new RollbackService($appConfig, $io);
            $release = $rollbackService->rollback($specificRelease);

            $io->success("Application {$appConfig['name']} has been rolled back to release: {$release}");

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $io->error("Rollback failed: {$e->getMessage()}");

            return Command::FAILURE;
        }
    }
}
