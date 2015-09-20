<?php

namespace Nacatamal\Internals;

use Nacatamal\Parser\ConfigParser;

class NacatamalInternals {
    private $storeReleasesDir;
    private $storeGitRepositoryDir;
    private $loggingDir;

    public function __construct() {
        $nacatamalHome = dirname(dirname(dirname(__DIR__)));
        $this->storeReleasesDir = $nacatamalHome . "/.internals/releases";
        $this->storeGitRepositoryDir = $nacatamalHome . "/.internals/repositories";
        $this->loggingDir = $nacatamalHome . "/.internals/logging";
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
        $buildCountFile = $this->loggingDir . "/{$projectName}_build";
        if (!file_exists($buildCountFile)) {
            file_put_contents($buildCountFile, 1);
            $buildNumber = 1;
        } else {
            $updatedBuildNumber = (int)file_get_contents($buildCountFile) + 1;
            file_put_contents($buildCountFile, $updatedBuildNumber);
            $buildNumber = $updatedBuildNumber;
        }

        return $buildNumber;
    }

    public function sortByNewest(&$toSort) {
        $reindex = array();
        foreach ($toSort as $t) {
            preg_match("/_\d+/", $t, $output);
            $reindex[substr($output[0], 1)] = $t;
        }

        ksort($reindex);

        return $reindex;
    }

    public function getReleaseStoreNumber(ConfigParser $configParser) {
        $defaults = $configParser->getDefaults();

        return $defaults["store_up_to"];
    }

    public function getReleasesStoredInFile() {
        $packagedReleases = scandir($this->storeReleasesDir);
        $countFiles = count($packagedReleases) - 2;
        return $countFiles;
    }
} 