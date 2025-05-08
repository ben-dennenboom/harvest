<?php

namespace Dennenboom\Harvest\Commands;

use Dennenboom\Harvest\Services\DeploymentService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class DeployCommand extends Command
{
    /**
     * The configuration data.
     *
     * @var array
     */
    protected $config;

    /**
     * Create a new DeployCommand instance.
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
            ->setName('deploy')
            ->setDescription('Deploy a Laravel application')
            ->addArgument('app', InputArgument::OPTIONAL, 'The application to deploy')
            ->addOption('branch', 'b', InputOption::VALUE_REQUIRED, 'The branch to deploy')
            ->addOption('no-tests', null, InputOption::VALUE_NONE, 'Skip running tests')
            ->addOption('no-migrations', null, InputOption::VALUE_NONE, 'Skip running migrations')
            ->addOption('force', 'f', InputOption::VALUE_NONE, 'Force deployment even if tests fail');
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
        $io->title('Harvest: Zero Downtime Laravel Deployment');

        $appName = $input->getArgument('app') ?? $this->config['default_app'];

        if (!isset($this->config['applications'][$appName])) {
            $io->error("Application '{$appName}' not found in configuration.");

            return Command::FAILURE;
        }

        $appConfig = $this->config['applications'][$appName];

        if ($input->getOption('branch')) {
            $appConfig['branch'] = $input->getOption('branch');
        }

        $options = [
            'skip_tests'      => $input->getOption('no-tests'),
            'skip_migrations' => $input->getOption('no-migrations'),
            'force'           => $input->getOption('force'),
        ];

        $io->section("Deploying application: {$appConfig['name']}");
        $io->text("Repository: {$appConfig['repository']}");
        $io->text("Branch: {$appConfig['branch']}");
        $io->newLine();

        try {
            $deploymentService = new DeploymentService($appConfig, $this->config, $io);
            $deploymentService->deploy($options);

            $io->success("Application {$appConfig['name']} has been successfully deployed!");

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $io->error("Deployment failed: {$e->getMessage()}");

            return Command::FAILURE;
        }
    }
}
