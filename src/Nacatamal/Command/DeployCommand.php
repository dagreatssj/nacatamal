<?php

namespace Nacatamal\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class DeployCommand extends Command {
    public function configure() {
        $defs = array(
            new InputOption('build', null, InputOption::VALUE_REQUIRED,
                'Build number of release candidate or use lastest keyword', null)
        );

        $this->setName('deploy')
            ->setDefinition($defs)
            ->setDescription("This will deploy a new build to a server.");
    }

    public function execute(InputInterface $inputInterface, OutputInterface $outputInterface) {
        $build = $inputInterface->getOption('build');

        if (empty($build)) {
            throw new \RuntimeException("No build given.");
        }

        // get the build number and send it to the server
        $outputInterface->writeln("<comment>Ready to deploy " . $build . " to server</comment>");
    }
}