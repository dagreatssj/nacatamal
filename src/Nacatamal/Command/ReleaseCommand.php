<?php

namespace Nacatamal\Command;

use Nacatamal\Parser\ConfigParser;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class ReleaseCommand extends Command {
    public function configure() {
        $defs = array(
            new InputOption('list', null, InputOption::VALUE_OPTIONAL,
                "lists project's available release candidates. Use --list=all to show all", null),
            new InputOption('package', null, InputOption::VALUE_OPTIONAL, 'packages source code', null),
            new InputOption('commit', null, InputOption::VALUE_OPTIONAL, 'specify what committed code to package', null)
        );

        $this->setName('release')
            ->setDefinition($defs)
            ->setDescription("Usage: --list=project, for any use all or use --package=project");
    }

    public function execute(InputInterface $inputInterface, OutputInterface $outputInterface) {
        $configParser = new ConfigParser();
        $list = $inputInterface->getOption('list');
        $package = $inputInterface->getOption('package');

        if (empty($list) && empty($package)) {
            throw new \RuntimeException("Use --list==all to see projects available");
        } else if ($package && empty($list)) {
            if (!$this->doesProjectExist($configParser, $package)) {
                throw new \RuntimeException("Project name given does not exist. Please define one in config.yml");
            }

            $outputInterface->writeln("<info>Packaging $package project...</info>");
            $projectParams = $configParser->getProjectParams($package);

            // params for project
            $repository = $projectParams["repository"];
            $saveReleasesDir = $projectParams["save_releases_in_dir"];
            $jenkins = $projectParams["jenkins"];
            $workspace = $projectParams["workspace"];
            $originName = $projectParams["origin_name"];
            $branch = $projectParams["branch"];
            $buildCountFile = __DIR__ . "/../../../config/.{$package}_build";

            if ($jenkins == false) {
                $outputInterface->writeln("<comment>\nLooking for saved repository in $saveReleasesDir</comment>");
                $check = $this->checkForExistingClonedRepository($saveReleasesDir, $package);
                if ($check == false) {
                    $outputInterface->writeln("No repository found, cloning latest...");
                    system("cd $saveReleasesDir && git clone $repository");
                } else {
                    $outputInterface->writeln("updating repository to latest changes");
                    system("cd {$saveReleasesDir}/{$package} && git pull {$originName} ${branch}");
                }

                $outputInterface->writeln("<comment>\nDisplaying Git changes</comment>");
                system("cd {$saveReleasesDir}/{$package} && git log -1");
                $commitNumber = exec("cd {$saveReleasesDir}/{$package} && git log --pretty=format:\"%h\" -1");
            } else {
                echo "jenkins information display here";
                exit;
            }

            $outputInterface->writeln("<comment>\nCreating tarball, please wait...</comment>");

            if (!file_exists($buildCountFile)) {
                file_put_contents($buildCountFile, 1);
                $buildNumber = 1;
            } else {
                $updatedBuildNumber = (int)file_get_contents($buildCountFile) + 1;
                file_put_contents($buildCountFile, $updatedBuildNumber);
                $buildNumber = $updatedBuildNumber;
            }

            $tarballName = "{$package}_{$commitNumber}_{$buildNumber}";
            system("cd $saveReleasesDir && tar -cf {$tarballName}.tar $package");
            system("cd $saveReleasesDir && gzip {$tarballName}.tar");
            system("cd $saveReleasesDir && mv {$tarballName}.tar.gz $saveReleasesDir");

            $outputInterface->writeln("<info>\nRelease Candidate created in {$saveReleasesDir}/{$tarballName}.tar.gz</info>");
        } else if(!isset($list)) {
            throw new \RuntimeException("Use --list=all to show all or use project name to specify");
        } else {
            if ($list == "all" && empty($package)) {
                $this->listAll($outputInterface, $configParser);
            } else if ($list && empty($package)) {
                $projects = $configParser->getProjects();

                $foundProject = false;
                foreach ($projects as $main) {
                    foreach ($main as $projectName => $params) {
                        if ($projectName == $list) {
                            $foundProject = true;
                            break;
                        }
                    }
                }

                if ($foundProject) {
                    $outputInterface->writeln("<info>Displaying packages for: $list</info>");
                } else {
                    throw new \RuntimeException("Project does not exist. Define one in config.yml");
                }
            }else {
                throw new \RuntimeException("Invalid");
            }
        }
    }

    private function listAll(OutputInterface $outputInterface, ConfigParser $configParser) {
        $outputInterface->writeln("<comment>Showing all project's Release Candidates:</comment>");
        $projects = $configParser->getProjects();
        $projectNames = array();
        $projectParams = array();

        foreach ($projects as $main) {
            foreach ($main as $projectName => $params) {
                array_push($projectNames, $projectName);
                array_push($projectParams, $params);
            }
        }

        $i = 0;
        foreach ($projectNames as $pn) {
            $outputInterface->writeln("<info>Displaying packages for: $pn</info>");
            $directoryOfReleases = $projectParams[$i]["save_releases_in_dir"];

            $collectedFiles = array();
            if ($handle = opendir($directoryOfReleases)) {
                while (false !== ($entry = readdir($handle))) {
                    if ($entry != "." && $entry != "..") {
                        array_push($collectedFiles, $entry);
                    }
                }
            }

            $collectedFiles = $this->sortByNewest($collectedFiles);
            $collectedFiles = array_reverse($collectedFiles);
            foreach ($collectedFiles as $key => $file) {
                $outputInterface->writeln("- {$file}");
            }
            $i++;
        }
    }

    private function checkForExistingClonedRepository($lookFor, $projectName) {
        $cloneRepoExists = false;

        if ($handle = opendir($lookFor)) {
            while (false !== ($dir = readdir($handle))) {
                if ($dir != "." && $dir != "..") {
                    if ($dir == $projectName) {
                        $cloneRepoExists = true;
                    }
                }
            }
        }

        return $cloneRepoExists;
    }

    private function sortByNewest(&$toSort) {
        $reindex = array();
        foreach ($toSort as $t) {
            preg_match("/_\d+/", $t, $output);
            $reindex[substr($output[0],1)] = $t;
        }

        ksort($reindex);

        return $reindex;
    }

    private function doesProjectExist(ConfigParser $configParser, $paramGiven) {
        $projectExistance = false;
        $projects = $configParser->getProjects();

        foreach ($projects as $main) {
            foreach ($main as $projectName => $params) {
                if ($projectName == $paramGiven) {
                    $projectExistance = true;
                }
            }
        }

        return $projectExistance;
    }
}