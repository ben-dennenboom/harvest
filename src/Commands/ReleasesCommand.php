<?php

namespace Dennenboom\Harvest\Commands;

use Dennenboom\Harvest\Services\DeploymentService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class ReleasesCommand extends Command
{
    /**
     * The configuration data.
     *
     * @var array
     */
    protected $config;

    /**
     * Create a new ReleasesCommand instance.
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
            ->setName('releases')
            ->setDescription('List all releases for an application')
            ->addArgument('app', InputArgument::OPTIONAL, 'The application to list releases for');
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
        $io->title('Harvest: Laravel Releases');

        $appName = $input->getArgument('app') ?? $this->config['default_app'];

        if (!isset($this->config['applications'][$appName])) {
            $io->error("Application '{$appName}' not found in configuration.");

            return Command::FAILURE;
        }

        $appConfig = $this->config['applications'][$appName];

        $io->section("Releases for application: {$appConfig['name']}");

        try {
            $deploymentService = new DeploymentService($appConfig, $this->config, $io);
            $releases = $deploymentService->listReleases();

            if (empty($releases)) {
                $io->warning("No releases found for {$appConfig['name']}");

                return Command::SUCCESS;
            }

            $currentRelease = $deploymentService->getCurrentRelease();

            $tableRows = [];
            foreach ($releases as $index => $release) {
                $releaseDate = new \DateTime('@' . $release['timestamp']);
                $isCurrent = ($currentRelease && $release['name'] === $currentRelease);

                $tableRows[] = [
                    $index + 1,
                    $release['name'],
                    $releaseDate->format('Y-m-d H:i:s'),
                    $isCurrent ? 'Yes' : 'No',
                ];
            }

            $io->table(['#', 'Release', 'Date', 'Current'], $tableRows);

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $io->error("Failed to list releases: {$e->getMessage()}");

            return Command::FAILURE;
        }
    }
}
