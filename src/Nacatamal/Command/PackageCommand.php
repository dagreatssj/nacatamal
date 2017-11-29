<?php

namespace Nacatamal\Command;

use Nacatamal\Internals\NacatamalInternals;
use Nacatamal\Parser\ConfigParser;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class PackageCommand extends Command {
    private $nctmlRepoPrefix = "nctmlRepo_";

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
            ),
            new InputOption(
                'zip-compress', 'z',
                InputOption::VALUE_NONE,
                "use zip compression"
            ),
            new InputOption(
                'encrypt', 'e',
                InputOption::VALUE_NONE,
                "use password for zip compression"
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
        $zipCompress = $inputInterface->getOption('zip-compress');
        $encrypt = $inputInterface->getOption('encrypt');
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

            if ($encrypt) {
                $password = $configParser->getPassword($project);
                if (!empty($password) || !is_null($password)) {
                    $encryptString = "--password {$password} ";
                } else {
                    throw new \RuntimeException("<error>Password is empty, please enter a value in project.yml.</error>");
                }
            } else {
                $encryptString = "";
            }

            $prePackageCmd = $configParser->getPrePackageParams($project);

            if (!empty($prePackageCmd)) {
                $runPrePackageCommand = true;
            }

            // params for project
            $location = $projectParams["location"];
            $jenkins = preg_match('/git/', $location, $matches); // preg_match returns 1 if pattern is matched
            $jenkinsBuildNumberVar = getenv('BUILD_NUMBER');
            if (!$jenkinsBuildNumberVar) {
                $jenkins = 1; // better indicator, if Jenkins env variables are not injected then no Jenkins
            }
            $storedPackagesDir = $nacatamalInternals->getStorePackagesDir($configParser, $project);
            $originName = $projectParams["origin_name"];
            $branch = $projectParams["branch"];
            $localSavedRepositoryDir = $nacatamalInternals->getStoreGitRepositoryDir($configParser, $project);

            $projectRepoDir = "{$localSavedRepositoryDir}/{$this->nctmlRepoPrefix}{$project}";
            $projectRepoGitDir = $projectRepoDir;

            if ($jenkins == 1) {
                $outputInterface->writeln("<comment>\nLooking for saved repository in $localSavedRepositoryDir</comment>");
                $check = $this->checkForExistingClonedRepository($localSavedRepositoryDir, $project);
                if ($check == false) {
                    $outputInterface->writeln("No repository found, cloning latest...");
                    system("mkdir -p $projectRepoDir");
                    system("git clone $location $projectRepoDir");
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

            $packageList = $nacatamalInternals->getPackageCandidates($storedPackagesDir, $project, $zipCompress);
            $ifExists = $this->checkForExistingPackages($packageList, $commitNumber);
            if ($ifExists == false) {
                if ($runPrePackageCommand == true) {
                    $outputInterface->writeln("<info>Running pre package script {$prePackageCmd}</info>");
                    system("cd {$projectRepoGitDir} && {$prePackageCmd}");
                }
                $outputInterface->writeln("<comment>\nCreating tarball, please wait...</comment>");
                $packageName = "{$project}_" . $nacatamalInternals->getBuildCountFileNumber($project, $jenkinsBuildNumberVar) . "_{$commitNumber}";

                if ($jenkins == 0) {
                    $jenkinsWorkspaceProjectName = explode("/", $location);
                    $projectGitRepositoryDirName = end($jenkinsWorkspaceProjectName);
                } else {
                    $projectGitRepositoryDirName = substr($projectRepoDir, strrpos($projectRepoDir, '/') + 1);
                    $projectRepoDir = dirname($projectRepoDir);
                }

                $ignoreFiles = $configParser->getIgnoreParams($project, $pass);
                if (!empty($ignoreFiles)) {
                    $excludePattern = $this->excludeTheseFiles($ignoreFiles, $zipCompress, $projectGitRepositoryDirName, $packageName);
                }

                $this->createPackage($projectRepoDir, $packageName, $projectGitRepositoryDirName, $excludePattern, $encryptString, $storedPackagesDir, $zipCompress);

                $outputInterface->writeln("<info>\nRelease Candidate created in {$storedPackagesDir} as {$packageName}</info>");
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

    /**
     * Prints out all of the tarballs created
     * @param OutputInterface $outputInterface
     * @param ConfigParser $configParser
     * @param NacatamalInternals $nacatamalInternals
     */
    private function listAll(OutputInterface $outputInterface,
                             ConfigParser $configParser,
                             NacatamalInternals $nacatamalInternals) {
        $outputInterface->writeln("<comment>\nListing all project's release candidates (tarballs)</comment>");
        $projectNames = $configParser->getListOfProjects();

        $i = 0;
        foreach ($projectNames as $projectName) {
            $outputInterface->writeln("\n<info>$projectName</info>");
            $directoryOfReleases = $nacatamalInternals->getStorePackagesDir($configParser, $projectName);

            $collectedFiles = array();
            $savedRepoDir = "{$this->nctmlRepoPrefix}{$projectName}";
            if ($handle = opendir($directoryOfReleases)) {
                while (false !== ($entry = readdir($handle))) {
                    if ($entry != "." && $entry != ".." && $entry != $savedRepoDir) {
                        array_push($collectedFiles, $entry);
                    }
                }
            }

            $collectedFiles = $nacatamalInternals->sortByNewest($collectedFiles);
            $collectedFiles = array_reverse($collectedFiles);
            foreach ($collectedFiles as $key => $file) {
                if (strpos($file, $projectName) !== false) {
                    $outputInterface->writeln("-- {$file}");
                }
            }
            $i++;
        }
    }

    /**
     * check to see if nctmlRepo_{project} folder exists
     * @param $lookFor
     * @param $projectName
     * @return bool
     */
    private function checkForExistingClonedRepository($lookFor, $projectName) {
        $cloneRepoExists = false;
        $nacatamalRepositoryDir = "{$this->nctmlRepoPrefix}{$projectName}";

        if ($handle = opendir($lookFor)) {
            while (false !== ($dir = readdir($handle))) {
                if ($dir != "." && $dir != "..") {
                    if ($dir == $nacatamalRepositoryDir) {
                        $cloneRepoExists = true;
                    }
                }
            }
        }

        return $cloneRepoExists;
    }

    /**
     * Looks through configuration file to see if project has been listed
     * @param ConfigParser $configParser
     * @param $paramGiven
     * @return bool
     */
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

    /**
     * Create the command line string to exclude files for tar and zip.
     * @param $ignoreFiles
     * @param $zipCompress
     * @param $directoryName
     * @param $packageName
     * @return string
     */
    private function excludeTheseFiles($ignoreFiles, $zipCompress, $directoryName, $packageName) {
        $excludeString = "";
        $directoryName = ($zipCompress ? $packageName : $directoryName);

        foreach ($ignoreFiles as $f) {
            if (!empty($f)) {
                if ($zipCompress) {
                    $excludeString .= "{$directoryName}/{$f}\\* ";
                } else {
                    $excludeString .= "--exclude={$f} ";
                }
            }
        }

        if ($zipCompress) {
            $excludeString = "-x " . $excludeString;
        }
        return $excludeString;
    }

    /**
     * Given a project check to see if it's under the allowed packages to keep, if not then delete the oldest one.
     * @param NacatamalInternals $nacatamalInternals
     * @param ConfigParser $configParser
     * @param OutputInterface $outputInterface
     * @param $projectName
     */
    private function cleanUpTarballs(NacatamalInternals $nacatamalInternals,
                                     ConfigParser $configParser,
                                     OutputInterface $outputInterface,
                                     $projectName) {
        $packageStoreNumber = $nacatamalInternals->getPackageStoreNumber($configParser);
        $packagesStored = $nacatamalInternals->getPackagesStored($projectName);

        if (count($packagesStored) > (int)$packageStoreNumber) {
            $outputInterface->writeln("<comment>\nStored packages capacity of {$packageStoreNumber} has been reached, deleting earliest tarball {$packagesStored[0]}\n</comment>");
            unlink("{$nacatamalInternals->getStorePackagesDir($configParser, $projectName)}/{$packagesStored[0]}");
        }
    }

    /**
     * Looks through the list of packages to see if the current one being created has already been created.
     * @param $packagesInDir
     * @param $commitNumber
     * @return bool
     */
    private function checkForExistingPackages($packagesInDir, $commitNumber) {
        if (!empty($packagesInDir)) {
            foreach ($packagesInDir as $pkg) {
                $getCommitInPackage = explode("_", $pkg);
                $removeTarExtension = explode(".", $getCommitInPackage[2]);
                if ($commitNumber == $removeTarExtension[0]) {
                    return true;
                }
            }
        }
        return false;
    }

    /**
     * Use Linux Tar or Zip command to create a zip or tar file and move it to a directory.
     * @param $projectRepoDir
     * @param $compressedFilename
     * @param $projectGitRepositoryDirName
     * @param $excludePattern
     * @param $encryptZipString
     * @param $storedPackagesDir
     * @param $zipCompression
     */
    private function createPackage($projectRepoDir,
                                   $compressedFilename,
                                   $projectGitRepositoryDirName,
                                   $excludePattern,
                                   $encryptZipString,
                                   $storedPackagesDir,
                                   $zipCompression) {
        if ($zipCompression) {
            // no --transform equivalent in zip so temporarily rename the folder to desired name
            system("cd $projectRepoDir && mv $projectGitRepositoryDirName $compressedFilename");
            system("cd $projectRepoDir && zip -rq {$encryptZipString} {$compressedFilename}.zip {$compressedFilename} {$excludePattern}");
            system("cd $projectRepoDir && mv $compressedFilename $projectGitRepositoryDirName");
            system("cd $projectRepoDir && mv {$compressedFilename}.zip $storedPackagesDir");
        } else {
            system("cd $projectRepoDir && tar -cf {$compressedFilename}.tar {$projectGitRepositoryDirName} {$excludePattern} --transform='s/{$projectGitRepositoryDirName}/{$compressedFilename}/'");
            system("cd $projectRepoDir && mv {$compressedFilename}.tar $storedPackagesDir");
        }
    }
}