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
            )
        );

        $this
            ->setName('nacatamal:deploy')
            ->setDefinition($defs)
            ->setDescription("Uses SSH to send a tarball to a server.");
    }

    public function execute(InputInterface $inputInterface, OutputInterface $outputInterface) {
        $configParser = new ConfigParser();
        $nacatamalInternals = new NacatamalInternals();
        $project = $inputInterface->getOption('project');
        $build = $inputInterface->getOption('build');
        $server = $inputInterface->getOption('server');
        $pass = $inputInterface->getOption('include');
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
            $jenkinsEnabled = $projectParams["jenkins"];

            if ($postDeployParams != null) {
                $runnerForScript = $postDeployParams[$project]["runner"];
                $scriptToRun = $postDeployParams[$project]["script"];
                $runAPostScript = true;
            }

            $storedReleasesDir = $nacatamalInternals->getStoreReleasesDir();
            $sendReleasesDir = $projectParams["send_releases_to_dir"];
            $serverConfigurations = $configParser->getDeployTo($project, $server);
            $deploymentString =
                $serverConfigurations["username"] . "@" . $serverConfigurations["server"] . ":" . $sendReleasesDir;
            $sshString = $serverConfigurations["username"] . "@" . $serverConfigurations["server"];

            if ($build == "latest" && $jenkinsEnabled == false) {
                $projectRepoDir = "{$localSavedRepositoryDir}/for_{$project}";
                $inDir = array_diff(scandir($projectRepoDir), array('..', '.'));
                $projectGitRepositoryDirName = current($inDir);

                if (count($nacatamalInternals->getReleaseCandidates($storedReleasesDir)) == 0) {
                    $this->runPackageCommand($project, $outputInterface, $pass);
                }
                $commitNumber = exec("cd {$projectGitRepositoryDirName} && git log --pretty=format:\"%h\" -1");
                $latestBuildPackaged = $nacatamalInternals->getLatestReleaseCandidatePackaged($storedReleasesDir);

                $getCommitInTar = explode("_", $latestBuildPackaged);
                $commitInTar = $getCommitInTar[1];
                if ($commitInTar == $commitNumber) {
                    $outputInterface->writeln("<comment>Checking for a newer build...</comment>");
                    system("cd {$projectGitRepositoryDirName} && git pull {$originName} ${branch}");
                    $checkCommitNumberAgain =
                        exec("cd {$projectGitRepositoryDirName} && git log --pretty=format:\"%h\" -1");
                    if ($commitInTar != $checkCommitNumberAgain) {
                        $outputInterface->writeln("<comment>Newer build found, packaging...</comment>");
                        $this->runPackageCommand($project, $outputInterface, $pass);
                    }
                }

                $latestBuildPackaged = $nacatamalInternals->getLatestReleaseCandidatePackaged($storedReleasesDir);
                $deployLatestBuild = $latestBuildPackaged;
                $buildString = $deployLatestBuild;
                $outputInterface->writeln(
                    "<comment>Deploying latest build " . $deployLatestBuild . " to server</comment>"
                );
                $releaseDirectory = "{$storedReleasesDir}/{$deployLatestBuild}";
            } else {
                $latestBuildPackaged = $nacatamalInternals->getReleaseCandidates($storedReleasesDir);

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
                    $releaseDirectory = "{$storedReleasesDir}/{$deployThisBuild}";
                } else {
                    throw new \RuntimeException("Build Not Found");
                }
            }

            $getName = explode(".", $buildString);
            $proceed = $this->proceedWithDeploy(
                $outputInterface,
                $serverConfigurations["port"],
                $sshString,
                $sendReleasesDir,
                $buildString
            );
            if ($proceed) {
                if ($jenkinsEnabled) $project = "workspace";
                $outputInterface->writeln("Sending package to {$deploymentString}");
                system("scp -P {$serverConfigurations["port"]} {$releaseDirectory} {$deploymentString}");
                $outputInterface->writeln("<info>Unpackaging...</info>");
                system("ssh -p {$serverConfigurations["port"]} {$sshString} 'tar -zxvf {$sendReleasesDir}/{$buildString} -C {$sendReleasesDir}'");
                system("ssh -p {$serverConfigurations["port"]} {$sshString} 'mv {$sendReleasesDir}/{$project} {$sendReleasesDir}/{$getName[0]}'");
                if ($runAPostScript == true) {
                    $outputInterface->writeln("<info>Running post deploy script {$runnerForScript} {$sendReleasesDir}/{$getName[0]}/{$scriptToRun}</info>");
                    system("ssh -t -t -p {$serverConfigurations["port"]} {$sshString} 'cd {$sendReleasesDir}/{$getName[0]} && {$runnerForScript} {$scriptToRun}'");
                }
            }
        }
    }

    private function proceedWithDeploy(OutputInterface $outputInterface, $port, $ssh, $releasesDir,
                                       $releasesCandidate) {
        $getServerParam = explode("@", $ssh);
        $serverUrl = $getServerParam[1];

        $outputInterface->writeln("<info>Checking server status...</info>");
        exec("ping -c 5 {$serverUrl}", $output, $exitCode);

        if (!$exitCode) {
            $outputInterface->writeln("<info>Server is live and ready...</info>");
            $outputInterface->writeln("<info>... Checking existance of {$releasesDir}.</info>");
            system("ssh -p {$port} {$ssh} 'if [ -d {$releasesDir} ]; then exit 2; fi;'", $dirExists);
            if ($dirExists) {
                $outputInterface->writeln("<info>Directory {$releasesDir} exists.</info>");
                $outputInterface->writeln("<info>Checking previously deployments...</info>");
                system("ssh -p {$port} {$ssh} 'ls {$releasesDir}/{$releasesCandidate}'", $check);
                if ($check) {
                    $outputInterface->writeln("<info>{$releasesCandidate} is ready to deploy.</info>");
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