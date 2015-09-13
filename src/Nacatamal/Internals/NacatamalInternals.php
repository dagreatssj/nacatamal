<?php

namespace Nacatamal\Internals;

use Symfony\Component\Yaml\Parser;
use Symfony\Component\Yaml\Dumper;

class NacatamalInternals {
    protected $storeReleasesDir;
    protected $storeGitRepositoryDir;
    protected $loggingDir;

    public function __construct() {
        $this->storeReleasesDir = dirname(dirname(dirname(__DIR__))) . "/internals/releases";
        $this->storeGitRepositoryDir = dirname(dirname(dirname(__DIR__))) . "/internals/repositories";
        $this->loggingDir = dirname(dirname(dirname(__DIR__))) . "/internals/logging";
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
} 