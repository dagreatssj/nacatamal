<?php

namespace Nacatamal\Command;

use Nacatamal\Internals\NacatamalInternals;
use Nacatamal\Parser\ConfigParser;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class PackageCommand extends Command {
    public function configure() {
        $defs = array(
            new InputOption('list', null, InputOption::VALUE_OPTIONAL,
                "lists project's available release candidates. Use --list=all to show all", null),
            new InputOption('project', null, InputOption::VALUE_OPTIONAL, 'packages source code of a project', null)
        );

        $this->setName('package')
            ->setDefinition($defs)
            ->setDescription("Creates a tarball for source code. Usage: --list=project, for any use all or use --project=name_of_project to package source code");
    }

    public function execute(InputInterface $inputInterface, OutputInterface $outputInterface) {
        $configParser = new ConfigParser();
        $nacatamalInternals = new NacatamalInternals();
        $list = $inputInterface->getOption('list');
        $project = $inputInterface->getOption('project');

        if (empty($list) && empty($project)) {
            throw new \RuntimeException("Use --list all to see projects available");
        } else if ($project && empty($list)) {
            if (!$this->doesProjectExist($configParser, $project)) {
                throw new \RuntimeException("Project name given does not exist. Please define one in config.yml");
            }

            $outputInterface->writeln("<info>Creating a tarball for project: $project...</info>");
            $projectParams = $configParser->getProjectParams($project);
            $ignoreFiles = $configParser->getIgnoreParams($project);
            $excludePattern = $this->excludeTheseFiles($ignoreFiles);

            // params for project
            $repository = $projectParams["repository"];
            $saveReleasesDir = $nacatamalInternals->getStoreReleasesDir();
            $jenkins = $projectParams["jenkins"];
            $workspace = $projectParams["workspace"];
            $originName = $projectParams["origin_name"];
            $branch = $projectParams["branch"];
            $localSavedRepositoryDir = $nacatamalInternals->getStoreGitRepositoryDir();

            if ($jenkins == false) {
                $outputInterface->writeln("<comment>\nLooking for saved repository in $localSavedRepositoryDir</comment>");
                $check = $this->checkForExistingClonedRepository($localSavedRepositoryDir, $project);
                if ($check == false) {
                    $outputInterface->writeln("No repository found, cloning latest...");
                    system("cd $localSavedRepositoryDir && git clone $repository");
                } else {
                    $outputInterface->writeln("updating repository to latest changes");
                    system("cd {$localSavedRepositoryDir}/{$project} && git pull {$originName} ${branch}");
                }

                $outputInterface->writeln("<comment>\nDisplaying Git changes</comment>");
                system("cd {$localSavedRepositoryDir}/{$project} && git log -1");
                $commitNumber = exec("cd {$localSavedRepositoryDir}/{$project} && git log --pretty=format:\"%h\" -1");
            } else {
                // Jenkins
            }

            $outputInterface->writeln("<comment>\nCreating tarball, please wait...</comment>");
            $tarballName = "{$project}_{$commitNumber}_" . $nacatamalInternals->getBuildCountFileNumber($project);

            system("cd $localSavedRepositoryDir && tar -cf {$tarballName}.tar {$project} {$excludePattern}");
            system("cd $localSavedRepositoryDir && gzip {$tarballName}.tar");
            system("cd $localSavedRepositoryDir && mv {$tarballName}.tar.gz $saveReleasesDir");

            $outputInterface->writeln("<info>\nRelease Candidate created in {$saveReleasesDir}/{$tarballName}.tar.gz</info>");
        } else if (!isset($list)) {
            throw new \RuntimeException("Use --list=all to show all or use project name to specify");
        } else {
            if ($list == "all" && empty($project)) {
                $this->listAll($outputInterface, $configParser, $nacatamalInternals);
            } else if ($list && empty($project)) {
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
            } else {
                throw new \RuntimeException("Invalid");
            }
        }
    }

    private function listAll(OutputInterface $outputInterface, ConfigParser $configParser, NacatamalInternals $nacatamalInternals) {
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
            $directoryOfReleases = $nacatamalInternals->getStoreReleasesDir();

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
            $reindex[substr($output[0], 1)] = $t;
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

    private function excludeTheseFiles($ignoreFiles) {
        $excludeString = "";

        foreach ($ignoreFiles as $f) {
            $excludeString .= "--exclude={$f} ";
        }

        return $excludeString;
    }
}