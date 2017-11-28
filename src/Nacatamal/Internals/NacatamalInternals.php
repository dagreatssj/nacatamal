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

    public function getStoreGitRepositoryDir(ConfigParser $configParser, $project) {
        $projectYml = $configParser->getProjectParams($project);
        $projectInteralsSection = $projectYml['internals']['save_for_later_repositories'];
        if ($projectInteralsSection != null || !empty($projectInteralsSection)) {
            return $projectInteralsSection;
        } else {
            return $this->storeGitRepositoryDir;
        }
    }

    public function getStorePackagesDir(ConfigParser $configParser, $project) {
        $projectYml = $configParser->getProjectParams($project);
        $projectInteralsSection = $projectYml['internals']['location_to_store_packages'];
        if ($projectInteralsSection != null || !empty($projectInteralsSection)) {
            return $projectInteralsSection;
        } else {
            return $this->storePackagesDir;
        }
    }

    /**
     * Creates a file .projectname_buildcount that will keep count or updates the value. If Jenkins exists
     * then it will simply use the BUILD_NUMBER environmental variable
     * @param $projectName - name of the project
     * @param $jenkinsBuildNumber - BUILD_NUMBER value
     * @return string - transforms the number from 1 to 0001
     */
    public function getBuildCountFileNumber($projectName, $jenkinsBuildNumber) {
        if ($jenkinsBuildNumber) {
            $buildNumber = $jenkinsBuildNumber;
        } else {
            $buildCountFile = $this->logsDir . "/.{$projectName}_buildcount";
            if (!file_exists($buildCountFile)) {
                file_put_contents($buildCountFile, 1);
                $buildNumber = 1;
            } else {
                $updatedBuildNumber = (int)file_get_contents($buildCountFile) + 1;
                file_put_contents($buildCountFile, $updatedBuildNumber);
                $buildNumber = $updatedBuildNumber;
            }
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

    /**
     * Return an array list of the packages found in stored packages directory.
     * @param $storedPackagesDir
     * @param $project
     * @param $zipCompress
     * @return array
     */
    public function getPackageCandidates($storedPackagesDir, $project, $zipCompress) {
        $packages = array();
        $fileExt = "tar";

        if ($zipCompress) {
            $fileExt = "zip";
        }

        if ($handle = opendir($storedPackagesDir)) {
            while (false !== ($entry = readdir($handle))) {
                if ($entry != "." && $entry != "..") {
                    $tarFileExt = preg_match("/{$project}_.*.{$fileExt}/", $entry, $matches);
                    if ($tarFileExt == 1) {
                        array_push($packages, $entry);
                    }
                }
            }
        }

        return $packages;
    }

    public function getLatestReleaseCandidatePackaged($storedPackagesDir) {
        $builds = $this->getPackageCandidates($storedPackagesDir);
        $builds = $this->sortByNewest($builds);
        $latest = end($builds);

        return $latest;
    }
} 