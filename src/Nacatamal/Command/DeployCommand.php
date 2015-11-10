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
            new InputOption('project', null, InputOption::VALUE_REQUIRED,
                'Name of project to be deployed', null),
            new InputOption('build', null, InputOption::VALUE_REQUIRED,
                'Build number of release candidate or use lastest keyword', null),
            new InputOption('server', null, InputOption::VALUE_REQUIRED,
                'Server url you want to deploy to', null),
            new InputOption('pass', "p", InputOption::VALUE_NONE,
                "If set, will let exclude section files/folders in config file get packaged", null)
        );

        $this->setName('deploy')
            ->setDefinition($defs)
            ->setDescription("Usage: --build=number or use latest to automatically create one. --project=name to choose which project and --server=serverName to choose the server to deploy to.");
    }

    public function execute(InputInterface $inputInterface, OutputInterface $outputInterface) {
        $configParser = new ConfigParser();
        $nacatamalInternals = new NacatamalInternals();
        $project = $inputInterface->getOption('project');
        $build = $inputInterface->getOption('build');
        $server = $inputInterface->getOption('server');
        $pass = $inputInterface->getOption('pass');
        $localSavedRepositoryDir = $nacatamalInternals->getStoreGitRepositoryDir();
        $runAPostScript = false;
        $projectParams = $configParser->getProjectParams($project);
        $postDeployParams = $configParser->getPostDeployParams($project);

        if (empty($project) && empty($build) && empty($server)) {
            throw new \RuntimeException("Set project, build and server arguments.");
        } else if (empty($project)) {
            throw new \RuntimeException("A project is required.");
        } else if (empty($build)) {
            throw new \RuntimeException("latest keyword or a build number is required.");
        } else if (empty($server)) {
            throw new \RuntimeException("Set the name of the server to deploy, defined in deploy_to");
        } else {
            $originName = $projectParams["origin_name"];
            $branch = $projectParams["branch"];

            if ($postDeployParams != null) {
                $runnerForScript = $postDeployParams[$project]["runner"];
                $scriptToRun = $postDeployParams[$project]["script"];
                $runAPostScript = true;
            }

            $saveReleasesDir = $nacatamalInternals->getStoreReleasesDir();
            $sendReleasesDir = $projectParams["send_releases_to_dir"];
            $serverConfigurations = $configParser->getDeployTo($project, $server);
            $deploymentString =
                $serverConfigurations["username"] . "@" . $serverConfigurations["server"] . ":" . $sendReleasesDir;
            $sshString = $serverConfigurations["username"] . "@" . $serverConfigurations["server"];

            if ($build == "latest") {
                if (count($nacatamalInternals->getReleaseCandidates($saveReleasesDir)) == 0) {
                    $this->runPackageCommand($project, $outputInterface, $pass);
                }
                $commitNumber = exec("cd {$localSavedRepositoryDir}/{$project} && git log --pretty=format:\"%h\" -1");
                $latestBuildPackaged = $nacatamalInternals->getLatestReleaseCandidatePackaged($saveReleasesDir);

                $getCommitInTar = explode("_", $latestBuildPackaged);
                $commitInTar = $getCommitInTar[1];
                if ($commitInTar == $commitNumber) {
                    $outputInterface->writeln("<comment>Checking for a newer build...</comment>");
                    system("cd {$localSavedRepositoryDir}/{$project} && git pull {$originName} ${branch}");
                    $checkCommitNumberAgain =
                        exec("cd {$localSavedRepositoryDir}/{$project} && git log --pretty=format:\"%h\" -1");
                    if ($commitInTar != $checkCommitNumberAgain) {
                        $outputInterface->writeln("<comment>Newer build found, packaging...</comment>");
                        $this->runPackageCommand($project, $outputInterface, $pass);
                    }
                }

                $latestBuildPackaged = $nacatamalInternals->getLatestReleaseCandidatePackaged($saveReleasesDir);
                $deployLatestBuild = $latestBuildPackaged;
                $buildString = $deployLatestBuild;
                $outputInterface->writeln("<comment>Deploying latest build " . $deployLatestBuild .
                    " to server</comment>");
                $releaseDirectory = "{$saveReleasesDir}/{$deployLatestBuild}";
            } else {
                $latestBuildPackaged = $nacatamalInternals->getReleaseCandidates($saveReleasesDir);

                foreach ($latestBuildPackaged as $b) {
                    preg_match("/_\d+\.tar/", $b, $output);
                    $getBuildNumberOff = substr($output[0], 1);
                    $buildNumber = substr($getBuildNumberOff, 0, -4);
                    if ($buildNumber == $build || $b == $build) {
                        $deployThisBuild = $b;
                        break;
                    }
                }

                if (!isset($deployThisBuild)) {
                    $outputInterface->writeln("<info>Build ($build) was not found.</info>");
                    exit(3);
                }

                if (isset($deployThisBuild)) {
                    $buildString = $deployThisBuild;
                    $outputInterface->writeln("<comment>Deploying build " . $deployThisBuild . " to server</comment>");
                    $releaseDirectory = "{$saveReleasesDir}/{$deployThisBuild}";
                } else {
                    throw new \RuntimeException("Build Not Found");
                }
            }

            $getName = explode(".", $buildString);
            $proceed =
                $this->proceedWithDeploy($outputInterface, $serverConfigurations["port"], $sshString, $sendReleasesDir,
                    $buildString);
            if ($proceed) {
                $outputInterface->writeln("Sending package to {$deploymentString}");
                system("scp -P {$serverConfigurations["port"]} {$releaseDirectory} {$deploymentString}");
                $outputInterface->writeln("<info>Unpackaging...</info>");
                system("ssh -p {$serverConfigurations["port"]} {$sshString} 'tar -zxvf {$sendReleasesDir}/{$buildString} -C {$sendReleasesDir}'");
                system("ssh -p {$serverConfigurations["port"]} {$sshString} 'mv {$sendReleasesDir}/{$project} {$sendReleasesDir}/{$getName[0]}'");
                if ($runAPostScript == true) {
                    $outputInterface->writeln("<info>Running post deploy script {$runnerForScript} {$sendReleasesDir}/{$getName[0]}/{$scriptToRun}</info>");
                    system("ssh -t -p {$serverConfigurations["port"]} {$sshString} 'cd {$sendReleasesDir}/{$getName[0]} && {$runnerForScript} {$scriptToRun}'");
                }
            }
        }
    }

    private function proceedWithDeploy(OutputInterface $outputInterface, $port, $ssh, $releasesDir,
                                       $releasesCandidate) {
        $getServerParam = explode("@", $ssh);
        $serverUrl = $getServerParam[1];

        $outputInterface->writeln("<info>Checking server status...</info>");
        exec("ping {$serverUrl}", $output, $exitCode);

        if (!$exitCode) {
            $outputInterface->writeln("<info>Server is live and ready...</info>");
            $outputInterface->writeln("<info>... Checking existance of {$releasesDir}.</info>");
            system("ssh -p {$port} {$ssh} 'if [ -d {$releasesDir} ]; then exit 2; fi;'", $dirExists);
            if ($dirExists) {
                system("ssh -p {$port} {$ssh} 'ls {$releasesDir}/{$releasesCandidate}'", $check);
                if ($check) {
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
            return false;
        }
    }

    private function runPackageCommand($project, OutputInterface $outputInterface, $passParam) {
        $packageCommand = $this->getApplication()->find('package');
        $arguments = array(
            'command' => 'package',
            '--project' => "{$project}"
        );

        if ($passParam) {
            $arguments = array_merge($arguments, array("--pass" => true));
        }
        $packageInput = new ArrayInput($arguments);
        $packageCommand->run($packageInput, $outputInterface);
    }
}