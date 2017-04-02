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
            new InputOption(
                'list', 'l',
                InputOption::VALUE_OPTIONAL,
                "lists project's available release candidates. Default will show all."
            ),
            new InputOption(
                'project', 'p',
                InputOption::VALUE_OPTIONAL,
                'packages source code of a project'
            ),
            new InputOption(
                'include', 'i',
                InputOption::VALUE_NONE,
                "If set, exclude section will be included"
            )
        );

        $this
            ->setName('nacatamal:package')
            ->setDefinition($defs)
            ->setDescription("Tarballs a project's source code.");
    }

    public function execute(InputInterface $inputInterface, OutputInterface $outputInterface) {
        $configParser = new ConfigParser();
        $nacatamalInternals = new NacatamalInternals();
        $list = $inputInterface->getOption('list');
        $project = $inputInterface->getOption('project');
        $pass = $inputInterface->getOption('include');
        $excludePattern = "";
        $runPrePackageCommand = false;

        if (empty($list) && empty($project)) {
            throw new \RuntimeException("Use -l|--list all to see project's tarballs available");
        } else if ($project && empty($list)) {
            if (!$this->doesProjectExist($configParser, $project)) {
                throw new \RuntimeException("Project name given does not exist. Please use nacatamal:configure to define one.");
            }

            $outputInterface->writeln("<info>Creating a tarball for project: $project...</info>");
            $projectParams = $configParser->getProjectParams($project);
            $ignoreFiles = $configParser->getIgnoreParams($project, $pass);

            if (!empty($ignoreFiles)) {
                $excludePattern = $this->excludeTheseFiles($ignoreFiles);
            }

            $prePackageCmd = $configParser->getPrePackageParams($project);

            if (!empty($prePackageCmd)) {
                $runPrePackageCommand = true;
            }

            // params for project
            $location = $projectParams["location"];
            $jenkins = preg_match('/git/', $location, $matches);
            $saveReleasesDir = $nacatamalInternals->getStorePackagesDir();
            $originName = $projectParams["origin_name"];
            $branch = $projectParams["branch"];
            $localSavedRepositoryDir = $nacatamalInternals->getStoreGitRepositoryDir();

            $projectRepoDir = "{$localSavedRepositoryDir}/for_{$project}";
            $projectRepoGitDir = $projectRepoDir . "/{$project}";

            if ($jenkins == 1) {
                $outputInterface->writeln("<comment>\nLooking for saved repository in $localSavedRepositoryDir</comment>");
                $check = $this->checkForExistingClonedRepository($localSavedRepositoryDir, $project);
                if ($check == false) {
                    $outputInterface->writeln("No repository found, cloning latest...");
                    system("mkdir -p $projectRepoDir");
                    system("cd $projectRepoDir && git clone $location $project");
                } else {
                    $outputInterface->writeln("updating repository to latest changes");
                    system("cd {$projectRepoDir} && git pull {$originName} ${branch}");
                }
            } else {
                $projectRepoDir = dirname($location);
                $projectRepoGitDir = $location;
            }

            $outputInterface->writeln("<comment>\nDisplaying Git changes</comment>");
            system("cd {$projectRepoGitDir} && git log -1");
            $commitNumber = exec("cd {$projectRepoGitDir} && git log --pretty=format:\"%h\" -1");

            $packageList = $nacatamalInternals->getPackageCandidates($saveReleasesDir);
            $ifExists = $this->checkForExistingPackages($packageList, $commitNumber);
            if ($ifExists == false) {
                if ($runPrePackageCommand == true) {
                    $outputInterface->writeln("<info>Running pre package script {$prePackageCmd}</info>");
                    system("cd {$projectRepoGitDir} && {$prePackageCmd}");
                }
                $outputInterface->writeln("<comment>\nCreating tarball, please wait...</comment>");
                $tarballName = "{$project}_" . $nacatamalInternals->getBuildCountFileNumber($project) . "_{$commitNumber}";

                if ($jenkins == 0) {
                    $projectGitRepositoryDirName = "workspace";
                } else {
                    $inDir = array_diff(scandir($projectRepoDir), array('..', '.'));
                    $projectGitRepositoryDirName = current($inDir);
                }
                system("cd $projectRepoDir && tar -cf {$tarballName}.tar {$projectGitRepositoryDirName} {$excludePattern}");
                system("cd $projectRepoDir && gzip {$tarballName}.tar");
                system("cd $projectRepoDir && mv {$tarballName}.tar.gz $saveReleasesDir");

                $outputInterface->writeln("<info>\nRelease Candidate created in {$saveReleasesDir}/{$tarballName}.tar.gz</info>");
                $this->cleanUpTarballs($nacatamalInternals, $configParser, $outputInterface, $project);
            } else {
                throw new \RuntimeException("<error>{$commitNumber} is packaged and ready to be deployed.</error>");
            }
        } else if (!isset($list)) {
            throw new \RuntimeException("Use --list=all to show all or use project name to specify");
        } else {
            if ($list == "all" && empty($project)) {
                $this->listAll($outputInterface, $configParser, $nacatamalInternals);
            } else if ($list && empty($project)) {
                $projects = $configParser->getListOfProjects();

                $foundProject = false;
                foreach ($projects as $projectName) {
                    if ($projectName == $list) {
                        $foundProject = true;
                        break;
                    }
                }

                if ($foundProject) {
                    $outputInterface->writeln("<info>Displaying packages for: $list</info>");
                } else {
                    throw new \RuntimeException("Project does not exist.  Please use nacatamal:configure to define one.");
                }
            } else {
                throw new \RuntimeException("Invalid");
            }
        }
    }

    private function listAll(OutputInterface $outputInterface,
                             ConfigParser $configParser,
                             NacatamalInternals $nacatamalInternals) {
        $outputInterface->writeln("<comment>\nListing all project's release candidates (tarballs)\n</comment>");
        $projectNames = $configParser->getListOfProjects();

        $i = 0;
        foreach ($projectNames as $pn) {
            $outputInterface->writeln("<info>$pn</info>");
            $directoryOfReleases = $nacatamalInternals->getStorePackagesDir();

            $collectedFiles = array();
            if ($handle = opendir($directoryOfReleases)) {
                while (false !== ($entry = readdir($handle))) {
                    if ($entry != "." && $entry != "..") {
                        array_push($collectedFiles, $entry);
                    }
                }
            }

            $collectedFiles = $nacatamalInternals->sortByNewest($collectedFiles);
            $collectedFiles = array_reverse($collectedFiles);
            foreach ($collectedFiles as $key => $file) {
                if (strpos($file, $pn) !== false) {
                    $outputInterface->writeln("-- {$file}");
                }
            }
            $i++;
        }
    }

    private function checkForExistingClonedRepository($lookFor, $projectName) {
        $cloneRepoExists = false;

        if ($handle = opendir($lookFor)) {
            while (false !== ($dir = readdir($handle))) {
                if ($dir != "." && $dir != "..") {
                    if ($dir == "for_{$projectName}") {
                        $cloneRepoExists = true;
                    }
                }
            }
        }

        return $cloneRepoExists;
    }

    private function doesProjectExist(ConfigParser $configParser, $paramGiven) {
        $projectExistance = false;
        $projects = $configParser->getListOfProjects();

        foreach ($projects as $p) {
            if ($p == $paramGiven) {
                $projectExistance = true;
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

    private function cleanUpTarballs(NacatamalInternals $nacatamalInternals,
                                     ConfigParser $configParser,
                                     OutputInterface $outputInterface,
                                     $projectName) {
        $packageStoreNumber = $nacatamalInternals->getPackageStoreNumber($configParser);
        $packagesStored = $nacatamalInternals->getPackagesStored($projectName);

        if (count($packagesStored) > (int)$packageStoreNumber) {
            $outputInterface->writeln("<comment>\nStored packages capacity of {$packageStoreNumber} has been reached, deleting earliest tarball {$packagesStored[0]}\n</comment>");
            unlink("{$nacatamalInternals->getStorePackagesDir()}/{$packagesStored[0]}");
        }
    }

    private function checkForExistingPackages($builds, $commitNumber) {
        foreach ($builds as $b) {
            $getCommitInPackage = explode("_", $b);
            $removeTarExtension = explode(".", $getCommitInPackage[2]);
            if ($commitNumber == $removeTarExtension[0]) {
                return true;
            }
        }

        return false;
    }
}