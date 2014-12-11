<?php

namespace Nacatamal\Command;

use Nacatamal\Parser\ConfigParser;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class ReleasesCommand extends Command {
    public function configure() {
        $defs = array(
            new InputOption('list', 'l', InputOption::VALUE_OPTIONAL,
                "lists project's available release candidates. Use -l for all", null),
            new InputOption('package', null, InputOption::VALUE_OPTIONAL, 'packages source code', null),
            new InputOption('commit', null, InputOption::VALUE_OPTIONAL, 'specify what committed code to package', null)
        );

        $this->setName('releases')
            ->setDefinition($defs)
            ->setDescription("This will package a release candidate");
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
                        // take params and get directory files
                        /*if ($handle = opendir($this->candidatesDir)) {
                            while (false !== ($dir = readdir($handle))) {
                                if ($dir != "." && $dir != "..") {
                                    var_dump($dir);
                                }
                            }
                        }*/
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
            $tarballName = "{$package}_{$commitNumber}_{$buildNumber}.tar";
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
        $keepIns = array();

        foreach ($projects as $main) {
            foreach ($main as $projectName => $params) {
                array_push($projectNames, $projectName);
                //var_dump($params);
            }
        }

        foreach ($projectNames as $pn) {
            $outputInterface->writeln("<info>Displaying packages for: $pn</info>");
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
} 