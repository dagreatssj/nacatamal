<?php

namespace Nacatamal\Command;

use Nacatamal\Internals\NacatamalInternals;
use Nacatamal\Parser\ConfigParser;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class DeployCommand extends Command {
    public function configure() {
        $defs = array(
            new InputOption(
                'project', 'p',
                InputOption::VALUE_REQUIRED,
                'Name of project to be deployed'
            ),
            new InputOption(
                'build', 'b',
                InputOption::VALUE_REQUIRED,
                'Build number/Tarball name of release candidate or use latest keyword'
            ),
            new InputOption(
                'server', 's',
                InputOption::VALUE_REQUIRED,
                'Server url you want to deploy to'
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
            ->setName('nacatamal:deploy')
            ->setDefinition($defs)
            ->setDescription("Uses SSH to send a project tarball to a server.");
    }

    public function execute(InputInterface $inputInterface, OutputInterface $outputInterface) {
        $configParser = new ConfigParser();
        $nacatamalInternals = new NacatamalInternals();
        $project = $inputInterface->getOption('project');
        $build = $inputInterface->getOption('build');
        $server = $inputInterface->getOption('server');
        $runAPostScript = false;

        // Package commands
        $include = $inputInterface->getOption('include');
        $zipCompress = $inputInterface->getOption('zip-compress');
        $encrypt = $inputInterface->getOption('encrypt');

        if (empty($project) && empty($build) && empty($server)) {
            throw new \RuntimeException("Set project, build and server arguments.");
        } else if (empty($project)) {
            throw new \RuntimeException("A project is required.");
        } else if (empty($build)) {
            throw new \RuntimeException("latest keyword or a build number is required.");
        } else if (empty($server)) {
            throw new \RuntimeException("Set the name of the server to deploy, defined in deploy_to");
        } else {
            $projectParams = $configParser->getProjectParams($project);
            $postDeployRuntimeScript = $configParser->getPostDeployRuntimeScript($project);

            $originName = $projectParams["origin_name"];
            $branch = $projectParams["branch"];
            $jenkinsEnabled = getenv('BUILD_NUMBER');

            if ($postDeployRuntimeScript != null) {
                $runAPostScript = true;
            }

            $storedPackgesDir = $nacatamalInternals->getStoredPackagesDir($configParser, $project);
            $serverConfigurations = $configParser->getDeployTo($project, $server);
            $sendPackageToDir = $serverConfigurations["send_package_to_dir"];
            $deploymentString = $serverConfigurations["user"] .
                "@" . $serverConfigurations["ip"] .
                ":" . $serverConfigurations["send_package_to_dir"];
            $sshString = $serverConfigurations["user"] . "@" . $serverConfigurations["ip"];

            // default port for ssh is 22
            if ($serverConfigurations["port"] == null || empty($serverConfigurations["port"])) {
                $serverConfigurations["port"] = "22";
            }

            /**
             *  If --build=latest (-b latest) use package command and is not a Jenkins instance
             *  Else use the --build={package_0001_123456}
             */
            if ($build == "latest" && $jenkinsEnabled == false) {
                $projectGitRepositoryDirName = $nacatamalInternals->getNacatamalRepositoryDirPath($configParser, $project);

                // check to see if there is any packages
                if (count($nacatamalInternals->getPackageCandidates($storedPackgesDir, $project, $zipCompress)) == 0) {
                    $this->runPackageCommand($project, $outputInterface, $include, $zipCompress, $encrypt);
                }

                $commitNumber = exec("cd {$projectGitRepositoryDirName} && git log --pretty=format:\"%h\" -1");
                $latestBuildPackaged = $nacatamalInternals->getLatestReleaseCandidatePackaged($storedPackgesDir, $project, $zipCompress);
                $getCommitInTar = explode("_", $latestBuildPackaged);
                $commitInTar = $getCommitInTar[2];

                if ($commitInTar == $commitNumber) {
                    $outputInterface->writeln("<comment>Checking for a newer build...</comment>");
                    system("cd {$projectGitRepositoryDirName} && git pull {$originName} ${branch}");
                    $checkCommitNumberAgain =
                        exec("cd {$projectGitRepositoryDirName} && git log --pretty=format:\"%h\" -1");
                    if ($commitInTar != $checkCommitNumberAgain) {
                        $outputInterface->writeln("<comment>Newer build found, packaging...</comment>");
                        $this->runPackageCommand($project, $outputInterface, $include, $zipCompress, $encrypt);
                    }
                }

                $buildString = $latestBuildPackaged;
                $outputInterface->writeln(
                    "<comment>Deploying latest build " . $latestBuildPackaged . " to server</comment>"
                );
                $releaseDirectory = "{$storedPackgesDir}/{$latestBuildPackaged}";
            } else {
                $latestBuildPackaged = $nacatamalInternals->getPackageCandidates($storedPackgesDir, $project, $zipCompress);

                foreach ($latestBuildPackaged as $b) {
                    preg_match("/_\d+_/", $b, $output);
                    $getBuildNumberOff = substr($output[0], 1);
                    $buildNumber = substr($getBuildNumberOff, 0, -4);
                    if ($buildNumber == $build || $b == $build) {
                        $deployThisBuild = $b;
                        break;
                    }
                }

                if (isset($deployThisBuild)) {
                    $buildString = $deployThisBuild;
                    $outputInterface->writeln("<comment>Deploying build " . $deployThisBuild . " to server</comment>");
                    $releaseDirectory = "{$storedPackgesDir}/{$deployThisBuild}";
                } else {
                    throw new \RuntimeException("Build Not Found");
                }
            }

            $getName = explode(".", $buildString);
            $proceed = $this->proceedWithDeploy(
                $outputInterface,
                $serverConfigurations["port"],
                $sshString,
                $sendPackageToDir,
                $buildString
            );
            if ($proceed) {
                $outputInterface->writeln("\nSending package to {$deploymentString}");
                system("scp -P {$serverConfigurations["port"]} {$releaseDirectory} {$deploymentString}");

                $outputInterface->writeln("<info>Unpackaging...</info>");
                if ($zipCompress) {
                    system("ssh -p {$serverConfigurations["port"]} {$sshString} 'unzip {$sendPackageToDir}/{$buildString} -d {$sendPackageToDir}'");
                } else {
                    system("ssh -p {$serverConfigurations["port"]} {$sshString} 'tar -xvf {$sendPackageToDir}/{$buildString} -C {$sendPackageToDir}'");
                }

                if ($runAPostScript == true) {
                    $outputInterface->writeln("<info>Running post deploy script...</info>");
                    system("ssh -t -t -p {$serverConfigurations["port"]} {$sshString} 'cd {$sendPackageToDir}/{$getName[0]} && {$postDeployRuntimeScript}'");
                }
            }
        }
    }

    /**
     * Checks the servers configurations
     *
     * @param OutputInterface $outputInterface
     * @param $port - ssh port (default is 22)
     * @param $ssh - ssh string, e.g. ubuntu@nacatamal.com
     * @param $releasesDir - directory path found in send_package_to_dir
     * @param $releasesCandidate - name of the package file created by nacatamal
     * @return bool
     */
    private function proceedWithDeploy(OutputInterface $outputInterface,
                                       $port,
                                       $ssh,
                                       $releasesDir,
                                       $releasesCandidate) {
        $getServerParam = explode("@", $ssh);
        $serverUrl = $getServerParam[1];

        $outputInterface->writeln("\n<info>Checking server status...</info>");
        exec("ssh -p {$port} {$ssh} 'ls'", $output, $exitCode);

        if (!$exitCode) {
            $outputInterface->writeln("<info>Server is live and ready...</info>");
            $outputInterface->writeln("\n<comment>Checking existence of {$releasesDir}...</comment>");
            system("ssh -p {$port} {$ssh} 'if [ -d {$releasesDir} ]; then exit 2; fi;'", $dirExists);
            if ($dirExists) {
                $outputInterface->writeln("<info>Directory {$releasesDir} exists</info>");
                $outputInterface->writeln("\n<info>Checking previously deployments...</info>");
                system("ssh -p {$port} {$ssh} 'ls {$releasesDir}/{$releasesCandidate}'", $check);
                if ($check) {
                    $outputInterface->writeln("<comment>{$releasesCandidate} is ready to deploy.</comment>");
                    return true;
                } else {
                    $outputInterface->writeln("<error>{$releasesCandidate} has already been uploaded.</error>");
                    return false;
                }
            } else {
                $outputInterface->writeln("<info>Creating directory to send release candidate: {$releasesDir}</info>");
                system("ssh -p {$port} {$ssh} 'if [ ! -d {$releasesDir} ]; then mkdir -p {$releasesDir}; fi;'",
                    $exitCode);
                return true;
            }
        } else {
            throw new \RuntimeException("Server is not responding or is configured incorrectly. Check projects.yml deploy_to.");
        }
    }

    /**
     * Runs nacatamal:package command
     *
     * @param $project
     * @param OutputInterface $outputInterface
     * @param $include
     * @param $zipCompress
     * @param $encrypt
     */
    private function runPackageCommand($project, OutputInterface $outputInterface, $include, $zipCompress, $encrypt) {
        $packageCommand = $this->getApplication()->find('nacatamal:package');
        $arguments = array(
            'command' => 'nacatamal:package',
            '--project' => "{$project}"
        );

        if ($include) {
            $arguments = array_merge($arguments, array("--pass" => true));
        }
        if ($zipCompress) {
            $arguments = array_merge($arguments, array("--zip-compress" => true));
        }
        if ($encrypt) {
            $arguments = array_merge($arguments, array("--encrypt" => true));
        }
        $packageInput = new ArrayInput($arguments);
        $packageCommand->run($packageInput, $outputInterface);
    }
}
