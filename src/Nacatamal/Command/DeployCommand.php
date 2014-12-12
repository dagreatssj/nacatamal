<?php

namespace Nacatamal\Command;

use Nacatamal\Parser\ConfigParser;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class DeployCommand extends Command {
    public function configure() {
        $defs = array(
            new InputOption('project', null, InputOption::VALUE_REQUIRED,
                'name of the project to be deployed', null),
            new InputOption('build', null, InputOption::VALUE_REQUIRED,
                'Build number of release candidate or use lastest keyword', null),
            new InputOption('server', null, InputOption::VALUE_REQUIRED,
                'Name of the server you want to deploy to', null)
        );

        $this->setName('deploy')
            ->setDefinition($defs)
            ->setDescription("Usage: --build=project or use latest to automatically create one. --project=name to choose which project and --server=serverName to choose the server to deploy to.");
    }

    public function execute(InputInterface $inputInterface, OutputInterface $outputInterface) {
        $configParser = new ConfigParser();
        $project = $inputInterface->getOption('project');
        $build = $inputInterface->getOption('build');
        $server = $inputInterface->getOption('server');

        if (empty($project) && empty($build)) {
            throw new \RuntimeException("Use --project and --build");
        } else if (empty($project)) {
            throw new \RuntimeException("A project is required.");
        } else if (empty($build)) {
            throw new \RuntimeException("A build number is required.");
        } else {
            $projectParams = $configParser->getProjectParams($project);

            $saveReleasesDir = $projectParams["save_releases_in_dir"];
            $sendReleasesDir = $projectParams["send_releases_to_dir"];

            if ($build == "latest") {
                $builds = $this->getReleaseCandidates($saveReleasesDir);
                $builds = $this->sortByNewest($builds);
                $builds = array_reverse($builds);
                $deployLatestBuild = $builds[0];
                $outputInterface->writeln("<comment>Deploying latest build " . $deployLatestBuild . " to server</comment>");
            } else {
                $builds = $this->getReleaseCandidates($saveReleasesDir);

                foreach ($builds as $b) {
                    preg_match("/_\d+/", $b, $output);
                    $candidate = substr($output[0], 1);
                    if ($candidate == $build) {
                        $deployThisBuild = $b;
                        break;
                    }
                }

                if (isset($deployThisBuild)) {
                    $outputInterface->writeln("<comment>Deploying build " . $deployThisBuild . " to server</comment>");
                    $serverConfigurations = $configParser->getDeployTo($project, $server);

                    $deploymentString = $serverConfigurations["username"] . "@" . $serverConfigurations["server"] . ":" . $sendReleasesDir;
                    $releaseDirectory = "{$saveReleasesDir}/{$deployThisBuild}";
                    $outputInterface->writeln("Sending package to {$deploymentString}");
                    system("scp -P {$serverConfigurations["port"]} {$releaseDirectory} {$deploymentString}");
                } else {
                    throw new \RuntimeException("Build Not Found");
                }
            }
        }
    }

    private function getReleaseCandidates($saveReleasesDir) {
        $builds = array();

        if ($handle = opendir($saveReleasesDir)) {
            while (false !== ($entry = readdir($handle))) {
                if ($entry != "." && $entry != "..") {
                    array_push($builds, $entry);
                }
            }
        }

        return $builds;
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
}