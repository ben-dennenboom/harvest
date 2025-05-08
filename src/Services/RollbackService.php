<?php

namespace Dennenboom\Harvest\Services;

use Symfony\Component\Console\Style\SymfonyStyle;

class RollbackService
{
    /**
     * The application configuration.
     *
     * @var array
     */
    protected $appConfig;

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
     * The current path.
     *
     * @var string
     */
    protected $currentPath;

    /**
     * Create a new RollbackService instance.
     *
     * @param array $appConfig
     * @param \Symfony\Component\Console\Style\SymfonyStyle $io
     */
    public function __construct(array $appConfig, SymfonyStyle $io)
    {
        $this->appConfig = $appConfig;
        $this->io = $io;

        $this->appPath = $appConfig['path'];
        $this->releasesPath = "{$this->appPath}/releases";
        $this->currentPath = "{$this->appPath}/current";
    }

    /**
     * Rollback to a previous release.
     *
     * @param string|null $specificRelease
     *
     * @return string
     * @throws \Exception
     */
    public function rollback(?string $specificRelease = null): string
    {
        $releases = $this->getAvailableReleases();

        if (count($releases) < 2) {
            throw new \Exception('No releases available for rollback.');
        }

        $currentRelease = $this->getCurrentRelease();

        if (!$currentRelease) {
            throw new \Exception('No current release found.');
        }

        if ($specificRelease) {
            return $this->rollbackToSpecificRelease($specificRelease, $releases, $currentRelease);
        }

        return $this->rollbackToPreviousRelease($releases, $currentRelease);
    }

    /**
     * Get all available releases.
     *
     * @return array
     */
    protected function getAvailableReleases(): array
    {
        $releases = [];

        if (!is_dir($this->releasesPath)) {
            return [];
        }

        foreach (glob("{$this->releasesPath}/*", GLOB_ONLYDIR) as $releaseDir) {
            $releases[] = basename($releaseDir);
        }

        usort($releases, function ($a, $b) {
            return strcmp($b, $a);
        });

        return $releases;
    }

    /**
     * Get the current release.
     *
     * @return string|null
     */
    protected function getCurrentRelease(): ?string
    {
        if (!file_exists($this->currentPath) || !is_link($this->currentPath)) {
            return null;
        }

        $target = readlink($this->currentPath);

        return basename($target);
    }

    /**
     * Rollback to a specific release.
     *
     * @param string $specificRelease
     * @param array $releases
     * @param string $currentRelease
     *
     * @return string
     * @throws \Exception
     */
    protected function rollbackToSpecificRelease(
        string $specificRelease,
        array $releases,
        string $currentRelease
    ): string {
        if ($specificRelease === $currentRelease) {
            throw new \Exception("Already on release {$specificRelease}.");
        }

        $found = false;
        foreach ($releases as $release) {
            if ($release === $specificRelease) {
                $found = true;
                break;
            }
        }

        if (!$found) {
            throw new \Exception("Release {$specificRelease} not found.");
        }

        $this->io->text("Rolling back to release: {$specificRelease}");

        $releasePath = "{$this->releasesPath}/{$specificRelease}";
        $this->activateRelease($releasePath);

        return $specificRelease;
    }

    /**
     * Activate a release.
     *
     * @param string $releasePath
     *
     * @return void
     */
    protected function activateRelease(string $releasePath): void
    {
        $tempLink = "{$this->appPath}/release-temp";

        if (file_exists($tempLink)) {
            unlink($tempLink);
        }

        symlink($releasePath, $tempLink);
        rename($tempLink, $this->currentPath);
    }

    /**
     * Rollback to the previous release.
     *
     * @param array $releases
     * @param string $currentRelease
     *
     * @return string
     * @throws \Exception
     */
    protected function rollbackToPreviousRelease(array $releases, string $currentRelease): string
    {
        $previousRelease = null;

        foreach ($releases as $index => $release) {
            if ($release === $currentRelease) {
                if (isset($releases[$index + 1])) {
                    $previousRelease = $releases[$index + 1];
                    break;
                }
            }
        }

        if (!$previousRelease) {
            throw new \Exception('No previous release found for rollback.');
        }

        $this->io->text("Rolling back to previous release: {$previousRelease}");

        $releasePath = "{$this->releasesPath}/{$previousRelease}";
        $this->activateRelease($releasePath);

        return $previousRelease;
    }
}
