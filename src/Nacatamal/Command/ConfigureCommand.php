<?php

namespace Nacatamal\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Yaml\Yaml;

class ConfigureCommand extends Command {
    public function configure() {
        $defs = array(
            new InputOption(
                'project', 'p',
                InputOption::VALUE_REQUIRED,
                'Name of the project to create entry for.'
            ),
            new InputOption(
                'internals', '',
                InputOption::VALUE_NONE,
                'Set to create internals.yml file.'
            ),
            new InputOption(
                'manual', 'm',
                InputOption::VALUE_NONE,
                'Set to generate a project with blank fields and manually update them later.'
            ),
            new InputOption(
                'duplicate', 'd',
                InputOption::VALUE_REQUIRED,
                'Add the name of an existing project to duplicate.'
            )
        );

        $this
            ->setName('nacatamal:configure')
            ->setDefinition($defs)
            ->setDescription("Use this to create and manage projects.");
    }

    public function execute(InputInterface $inputInterface, OutputInterface $outputInterface) {
        $project = $inputInterface->getOption('project');
        $internals = $inputInterface->getOption('internals');
        $manual = $inputInterface->getOption('manual');
        $duplicate = $inputInterface->getOption('duplicate');

        if (empty($project) && empty($internals)) {
            throw new \RuntimeException("Project name is required.");
        } else if (!empty($internals)) {
            //TODO create internals file flow
            $outputInterface->writeln("<comment>create internals file</comment>");
        } else {
            if (!empty($manual) && empty($duplicate)) {
                // TODO create blank fields
                $outputInterface->writeln("<comment>create project with blank fields</comment>");
            } else if (!empty($duplicate) && empty($manual)) {
                // TODO duplicate projects
                $outputInterface->writeln("<comment>copy an exisiting thing</comment>");
            } else if (!empty($manual) && !empty($duplicate)) {
                throw new \RuntimeException("Can't set --manual and --duplicate option at the same time.");
            } else {
                // TODO check if file already exists don't override it but write an new entry
                // TODO check if project already exists stop input
                $helper = $this->getHelper('question');
                $projectsYamlParams = array();

                $outputInterface->writeln(
                    "<info>The following questions will generate projects.yml, bracket text are <comment>defaults</comment>" .
                    " in which you can simply hit enter to continue. </info>\n"
                );

                $locationQuestion = new Question('Please enter the Git URL or Jenkins workspace directory: ');
                $location = $helper->ask($inputInterface, $outputInterface, $locationQuestion);

                $originNameQuestion = new Question("Enter origin name [<comment>origin</comment>]: ");
                $originName = $helper->ask($inputInterface, $outputInterface, $originNameQuestion);
                if ($originName == null) {
                    $originName = "origin";
                }

                $branchQuestion = new Question("Enter branch [<comment>master</comment>]: ");
                $branch = $helper->ask($inputInterface, $outputInterface, $branchQuestion);
                if ($branch == null) {
                    $branch = "master";
                }

                $outputInterface->writeln(
                    "\n<info>For the following question you may hit enter to auto generate an environment named 'dev' with blank fields to be filled in later</info>"
                );
                $envParams = array();
                $environmentNameQuestion = new Question("Enter name of environment: ");
                $environmentName = $helper->ask($inputInterface, $outputInterface, $environmentNameQuestion);
                $envNameSkip = false;
                if ($environmentName == null || $environmentName == "skip") {
                    $envParams['dev']['send_package_to_dir'] = "/tmp";
                    $envParams['dev']['user'] = "";
                    $envParams['dev']['ip'] = "";
                    $envParams['dev']['port'] = "";
                    $envNameSkip = true;
                }

                if ($envNameSkip == false) {
                    $deployToSendPkgToDirQuestion = new Question("Enter directory to send packages to [<comment>/tmp</comment>]: ");
                    $deployToSendPkgToDir = $helper->ask($inputInterface, $outputInterface, $deployToSendPkgToDirQuestion);
                    if ($deployToSendPkgToDir == null) {
                        $envParams[$environmentName]['send_packages_to_dir'] = "/tmp";
                    }


                    $outputInterface->write(
                        "\n<info>The following requires that you enter it as username ipaddress port.</info>\n" .
                        "\t<info>e.g. ubuntu 192.168.45.150 22</info>\n" .
                        "<info>You may skip this step by pressing <comment>enter</comment>, this will simply add the fields with blank values.</info>"
                    );
                    $setServerOptionsQuestion = new Question("Enter the following with a space for user, ip and port (leave blank to write them in later): ");
                    $setServerOptions = $helper->ask($inputInterface, $outputInterface, $setServerOptionsQuestion);
                    if ($setServerOptions == null) {
                        $envParams[$environmentName]['user'] = "";
                        $envParams[$environmentName]['ip'] = "";
                        $envParams[$environmentName]['port'] = "";
                    } else {
                        $serverOptInputs = explode(" ", $setServerOptions);
                        $envParams[$environmentName]['user'] = "{$serverOptInputs[0]}";
                        $envParams[$environmentName]['ip'] = "{$serverOptInputs[1]}";
                        $envParams[$environmentName]['port'] = "{$serverOptInputs[2]}";
                    }
                }

                $temporaryList = array();
                $alwaysList = array("README.md", ".git", ".gitignore");
                $outputInterface->writeln(
                    "\n<info>Temporary section is used to ignore a list of file or folder names. Temporary means you may choose to " .
                    "include them during package command, <comment>php nacatamal nacatamal:package --include</comment> (More details available with --help).</info>"
                );
                $ignoreTempFileQuestion = new Question("Enter the name of a file or a folder to ignore: ");
                $ignoreTempFile = $helper->ask($inputInterface, $outputInterface, $ignoreTempFileQuestion);
                if ($ignoreTempFile == null && empty($temporaryList)) {
                    array_push($temporaryList, '');
                } else {
                    array_push($temporaryList, "{$ignoreTempFile}");
                }

                $outputInterface->writeln(
                    "\n<info>Always section is where you want to add file or folder names that you never want to be included in package. </info>" .
                    "<info>For convenience, .git, .gitignore, and README.md are already auto included.</info>"
                );
                $ignoreAlwaysFileQuestion = new Question("Enter the name of a file or folder to always ignore: ");
                $ignoreAlwaysFile = $helper->ask($inputInterface, $outputInterface, $ignoreAlwaysFileQuestion);
                if ($ignoreAlwaysFile != null) {
                    array_push($alwaysList, "{$ignoreAlwaysFile}");
                }

                $outputInterface->writeln(
                    "\n<info>The following will ask to enter a command in which you want ran during <comment>nacatamal:package</comment>. " .
                    "The next question will ask for a command in which you want ran during <comment>nacatamal:deploy</comment></info>"
                );
                $prepackageScriptCmdQuestion = new Question("Enter command to use for pre-package: ");
                $prepackageScriptCmd = $helper->ask($inputInterface, $outputInterface, $prepackageScriptCmdQuestion);
                if ($prepackageScriptCmd == null) {
                    $prepackageScriptCmd = "";
                }

                $postDeployScriptCmdQuestion = new Question("Enter command to use for post-deploy: ");
                $postDeployScriptCmd = $helper->ask($inputInterface, $outputInterface, $postDeployScriptCmdQuestion);
                if ($postDeployScriptCmd == null) {
                    $postDeployScriptCmd = "";
                }

                $projectsYamlParams['location'] = $location;
                $projectsYamlParams['origin_name'] = $originName;
                $projectsYamlParams['branch'] = $branch;
                $projectsYamlParams['deploy_to'] = $envParams;
                $projectsYamlParams['ignore']['temporary'] = $temporaryList;
                $projectsYamlParams['ignore']['always'] = $alwaysList;
                $projectsYamlParams['runtime_scripts']['pre_package'] = $prepackageScriptCmd;
                $projectsYamlParams['runtime_scripts']['post_deploy'] = $postDeployScriptCmd;
                var_dump($projectsYamlParams);

                $yaml = Yaml::dump(array($project => $projectsYamlParams), 4, 4);
                file_put_contents(__DIR__ . "/../../../config/projects.yml", $yaml);
            }
        }
    }

    public function setEnvironmentParams(InputInterface $inputInterface, OutputInterface $outputInterface) {

    }
}