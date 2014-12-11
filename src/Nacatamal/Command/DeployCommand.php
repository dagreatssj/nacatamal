<?php

namespace Nacatamal\Command;

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
                'Build number of release candidate or use lastest keyword', null)
        );

        $this->setName('deploy')
            ->setDefinition($defs)
            ->setDescription("Usage: --build=project or use latest to automatically create one. --project=name to choose which project");
    }

    public function execute(InputInterface $inputInterface, OutputInterface $outputInterface) {
        $project = $inputInterface->getOption('project');
        $build = $inputInterface->getOption('build');

        if (empty($project) && empty($build)) {
            throw new \RuntimeException("Use --project and --build");
        } else if (empty($project)) {
            throw new \RuntimeException("A project is required.");
        } else if (empty($build)) {
            throw new \RuntimeException("A build number is required.");
        } else {
            // get the build number and send it to the server
            $outputInterface->writeln("<comment>Ready to deploy " . $build . " to server</comment>");
        }
    }
}