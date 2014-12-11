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
            new InputOption('list', 'l', InputOption::VALUE_OPTIONAL,
                "lists project's available release candidates. Use -l for all", null),
            new InputOption('package', null, InputOption::VALUE_OPTIONAL, 'packages source code', null),
            new InputOption('commit', null, InputOption::VALUE_OPTIONAL, 'specify what committed code to package', null)
        );

        $this->setName('release')
            ->setDefinition($defs)
            ->setDescription("Display all available releases with -l or package a build.");
    }

    public function execute(InputInterface $inputInterface, OutputInterface $outputInterface) {
        $configParser = new ConfigParser();
        $list = $inputInterface->getOption('list');
        $package = $inputInterface->getOption('package');
        $buildCountFile = __DIR__ . "/../../../config/.build";

        if (empty($list) && empty($package)) {
            $this->listAll($outputInterface, $configParser);
        } else if ($list && empty($package)) {
            $projects = $configParser->getProjects();
            foreach ($projects as $main) {
                foreach ($main as $projectName => $params) {
                    if ($projectName == $list) {
                        $outputInterface->writeln("<info>Displaying packages for: $list</info>");
                    }
                }
            }
        } else if ($package) {
            $outputInterface->writeln("<info>Packaging $package project...</info>");
            $projectParams = $configParser->getProjectParams($package);

            // params for project
            $repository = $projectParams["repository"];
            $keepIn = $projectParams["keep_in"];
            $jenkins = $projectParams["jenkins"];
            $workspace = $projectParams["workspace"];
            $saveTo = $projectParams["save_to"];
            $originName = $projectParams["origin_name"];
            $branch = $projectParams["branch"];

            if (!file_exists($buildCountFile)) {
                file_put_contents($buildCountFile, 1);
                $buildNumber = 1;
            } else {
                $updatedBuildNumber = (int)file_get_contents($buildCountFile) + 1;
                file_put_contents($buildCountFile, $updatedBuildNumber);
                $buildNumber = $updatedBuildNumber;
            }

            $outputInterface->writeln("<comment>\nLooking for saved repository in $keepIn</comment>");
            $check = $this->checkForExistingClonedRepository($keepIn, $package);
            if ($check == false) {
                system("cd $keepIn && git clone $repository");
            } else {
                $outputInterface->writeln("updating repository to latest changes");
                system("cd {$keepIn}/{$package} && git pull {$originName} ${branch}");
            }

            $outputInterface->writeln("<comment>\nDisplaying Git changes</comment>");
            system("cd {$keepIn}/{$package} && git log -1");
            $commitNumber = exec("cd {$keepIn}/{$package} && git log --pretty=format:\"%h\" -1");

            $outputInterface->writeln("<comment>\nCreating tarball</comment>");
            $tarballName = "{$package}_{$commitNumber}_{$buildNumber}";
            //system("cd $keepIn && tar -cf {$tarballName}.tar $package");
            //system("cd $keepIn && gzip {$tarballName}.tar");
            //system("cd $keepIn && mv {$tarballName}.tar.gz $saveTo");

            $outputInterface->writeln("<info>\nRelease Candidate created in {$saveTo}/{$tarballName}.tar.gz...</info>");

        } else {
            throw new \RuntimeException("Must provide project name.");
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
            $directoryOfReleases = $projectParams[$i]["save_to"]; //rename this to release_dir

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
}