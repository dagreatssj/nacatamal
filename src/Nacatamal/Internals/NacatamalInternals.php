<?php

namespace Nacatamal\Internals;

use Nacatamal\Parser\ConfigParser;

class NacatamalInternals {
    private $storeReleasesDir;
    private $storeGitRepositoryDir;
    private $loggingDir;

    public function __construct() {
        $nacatamalHome = dirname(dirname(dirname(__DIR__)));
        $this->storeReleasesDir = $nacatamalHome . "/internals/releases";
        $this->storeGitRepositoryDir = $nacatamalHome . "/internals/repositories";
        $this->loggingDir = $nacatamalHome . "/logging";
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
    public function getStoreReleasesDir() {
        return $this->storeReleasesDir;
    }

    public function getBuildCountFileNumber($projectName) {
        $buildCountFile = $this->loggingDir . "/.{$projectName}_buildcount";
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

    public function getReleaseStoreNumber(ConfigParser $configParser) {
        $defaults = $configParser->getDefaults();

        return $defaults["store_up_to"];
    }

    public function getReleasesStored($projectName) {
        $packagedReleases = scandir($this->storeReleasesDir);
        $files = array();

        foreach ($packagedReleases as $pr) {
            if (strpos($pr, $projectName) !== false) {
                array_push($files, $pr);
            }
        }

        return $files;
    }

    public function getReleaseCandidates($saveReleasesDir) {
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
        $builds = $this->getReleaseCandidates($saveReleasesDir);
        $builds = $this->sortByNewest($builds);
        $latest = end($builds);

        return $latest;
    }
} 