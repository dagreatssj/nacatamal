<?php

namespace Nacatamal\Internals;

use Nacatamal\Parser\ConfigParser;

class NacatamalInternals {
    private $storePackagesDir;
    private $storeGitRepositoryDir;
    private $logsDir;

    public function __construct() {
        $nacatamalHome = dirname(dirname(dirname(__DIR__)));
        $this->storePackagesDir = $nacatamalHome . "/internals/packages";
        $this->storeGitRepositoryDir = $nacatamalHome . "/internals/saved_repositories";
        $this->logsDir = $nacatamalHome . "/internals/logs";
    }

    /**
     * @return string
     */
    public function getStoreGitRepositoryDir() {
        return $this->storeGitRepositoryDir;
    }

    /**
     * @return string
     */
    public function getStorePackagesDir() {
        return $this->storePackagesDir;
    }

    public function getBuildCountFileNumber($projectName) {
        $buildCountFile = $this->logsDir . "/.{$projectName}_buildcount";
        if (!file_exists($buildCountFile)) {
            file_put_contents($buildCountFile, 1);
            $buildNumber = 1;
        } else {
            $updatedBuildNumber = (int)file_get_contents($buildCountFile) + 1;
            file_put_contents($buildCountFile, $updatedBuildNumber);
            $buildNumber = $updatedBuildNumber;
        }

        return str_pad($buildNumber, 4, '0', STR_PAD_LEFT);
    }

    public function sortByNewest(&$toSort) {
        $reindex = array();
        foreach ($toSort as $t) {
            preg_match("/_\d+_/", $t, $output);
            $reindex[$output[0]] = $t;
        }

        ksort($reindex);

        return $reindex;
    }

    public function getPackageStoreNumber(ConfigParser $configParser) {
        $defaults = $configParser->getDefaults();

        return $defaults["store_up_to"];
    }

    public function getPackagesStored($projectName) {
        $packagedReleases = scandir($this->storePackagesDir);
        $files = array();

        foreach ($packagedReleases as $pr) {
            if (strpos($pr, $projectName) !== false) {
                array_push($files, $pr);
            }
        }

        return $files;
    }

    public function getPackageCandidates($saveReleasesDir) {
        $builds = array();

        if ($handle = opendir($saveReleasesDir)) {
            while (false !== ($entry = readdir($handle))) {
                if ($entry != "." && $entry != "..") {
                    array_push($builds, $entry);
                }
            }
        }

        return $builds;
    }

    public function getLatestReleaseCandidatePackaged($saveReleasesDir) {
        $builds = $this->getPackageCandidates($saveReleasesDir);
        $builds = $this->sortByNewest($builds);
        $latest = end($builds);

        return $latest;
    }
} 