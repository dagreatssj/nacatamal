<?php

namespace Nacatamal\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Yaml\Yaml;
use Nacatamal\Parser\ConfigParser;

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
                'quick', '',
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
        $manual = $inputInterface->getOption('quick');
        $duplicate = $inputInterface->getOption('duplicate');

        $helper = $this->getHelper('question');
        $projectsYamlParams = array();
        if (empty($project) && empty($internals)) {
            throw new \RuntimeException("Project name is required.");
        } else if (!empty($internals)) {
            //TODO create internals file flow
            $outputInterface->writeln("<comment>create internals file</comment>");
        } else {
            if (!empty($manual) && empty($duplicate)) {
                $this->checkForDuplicateEntries($outputInterface, $project);
                $envParams = $this->setDeployToSection($inputInterface, $outputInterface, $helper, true, false);
                $temporaryList = $this->setTempIgnoreSection($inputInterface, $outputInterface, $helper, true, false);
                $alwaysList = $this->setAlwaysIgnoreSection($inputInterface, $outputInterface, $helper, true, false);
                $projectsYamlParams['location'] = "";
                $projectsYamlParams['origin_name'] = "origin";
                $projectsYamlParams['branch'] = "master";
                $projectsYamlParams['deploy_to'] = $envParams;
                $projectsYamlParams['ignore']['temporary'] = $temporaryList;
                $projectsYamlParams['ignore']['always'] = $alwaysList;
                $projectsYamlParams['runtime_scripts']['pre_package'] = "";
                $projectsYamlParams['runtime_scripts']['post_deploy'] = "";
                $this->generateProjectsYamlFile($project, $projectsYamlParams);
            } else if (!empty($duplicate) && empty($manual)) {
                // TODO duplicate projects
                $outputInterface->writeln("<comment>copy an exisiting thing</comment>");
            } else if (!empty($manual) && !empty($duplicate)) {
                throw new \RuntimeException("Can't set --manual and --duplicate option at the same time.");
            } else {
                $this->checkForDuplicateEntries($outputInterface, $project);
                $outputInterface->writeln(
                    "<info>The following questions will generate projects.yml, text in brackets are <comment>defaults</comment>" .
                    "\nin which you can simply hit enter to continue. Hitting enter throughout the enter set of questions " .
                    "\nwill generate the file with mostly emtpy fields.</info>"
                );

                $locationQuestion = new Question('Please enter the Git URL or Jenkins workspace directory: ');
                $location = $helper->ask($inputInterface, $outputInterface, $locationQuestion);

                $originNameQuestion = new Question("Enter origin name <comment>[origin]</comment>: ");
                $originName = $helper->ask($inputInterface, $outputInterface, $originNameQuestion);
                if ($originName == null) {
                    $originName = "origin";
                }

                $branchQuestion = new Question("Enter branch <comment>[master]</comment>: ");
                $branch = $helper->ask($inputInterface, $outputInterface, $branchQuestion);
                if ($branch == null) {
                    $branch = "master";
                }

                // TODO need to add an ask for more question
                $envParams = $this->setDeployToSection($inputInterface, $outputInterface, $helper, false, false);
                $temporaryList = $this->setTempIgnoreSection($inputInterface, $outputInterface, $helper, false, false);
                $alwaysList = $this->setAlwaysIgnoreSection($inputInterface, $outputInterface, $helper, false, false);

                $outputInterface->writeln(
                    "\n<info>The last section are runtime scripts, which will be ran either right before packaging takes place or\n" .
                    "as soon as the package has been deployed to the server. Formatting is as follows:</info>" .
                    "\n\t<comment>/bin/sh /tmp/do_something.sh</comment>"
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
                $this->generateProjectsYamlFile($project, $projectsYamlParams);
            }
        }
    }

    private function checkForDuplicateEntries(OutputInterface $outputInterface, $project) {
        if (file_exists(__DIR__ . "/../../../config/projects.yml")) {
            $configParser = new ConfigParser();
            $availableProjects = $configParser->getListOfProjects();
            foreach ($availableProjects as $p) {
                if ($project == $p) {
                    $outputInterface->writeln(
                        "<error>Project {$project} already exists, please choose another project name.</error>"
                    );
                    exit;
                }
            }
        }
    }

    private function setDeployToSection(InputInterface $inputInterface, OutputInterface $outputInterface, $helper, $skipFlag, $more) {
        if ($skipFlag == false) {
            if ($more == false) {
                $outputInterface->writeln(
                    "\n<info>The following question you may hit enter to auto generate an environment named 'dev', " .
                    "\nthe section will be in projects.yml under 'dev' and it's values can be entered there.</info>"
                );
            }

            $environmentNameQuestion = new Question("Enter name of environment: ");
            $environmentName = $helper->ask($inputInterface, $outputInterface, $environmentNameQuestion);
            if ($environmentName == null || empty($environmentName)) {
                $skipFlag = true;
            }
        }

        $envParams = array();
        $skipEnvSetup = false;
        if ($skipFlag == true) {
            $envParams['dev']['send_package_to_dir'] = "/tmp";
            $envParams['dev']['user'] = "";
            $envParams['dev']['ip'] = "";
            $envParams['dev']['port'] = "";
            $skipEnvSetup = true;
        }

        if ($skipEnvSetup == false) {
            $deployToSendPkgToDirQuestion = new Question("Enter directory to send packages to <comment>[/tmp]</comment>: ");
            $deployToSendPkgToDir = $helper->ask($inputInterface, $outputInterface, $deployToSendPkgToDirQuestion);
            if ($deployToSendPkgToDir == null) {
                $envParams[$environmentName]['send_packages_to_dir'] = "/tmp";
            }


            if ($more == false) {
                $outputInterface->write(
                    "\n<info>The following requires that you enter the following in this format:</info>\n" .
                    "\t<comment>e.g. ubuntu 192.168.45.150 22</comment>\n" .
                    "<info>You may choose to skip by pressing <comment>enter</comment>, fields will be readily " .
                    "available in projects.yml.</info>\n"
                );
            }
            $setServerOptionsQuestion = new Question("Enter the following with a space in between (user, ip and port): ");
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

        return $envParams;
    }

    private function setTempIgnoreSection(InputInterface $inputInterface, OutputInterface $outputInterface, $helper, $skipFlag, $more) {
        $temporaryList = array();
        $ignoreTempFile = null;
        if ($skipFlag == false) {
            if ($more == false) {
                $outputInterface->writeln(
                    "\n<info>Temporary section is used to ignore a list of file or folder names. Temporary means you may choose to " .
                    "\ninclude them during package command\n\ti.e. <comment>php nacatamal nacatamal:package --include</comment>" .
                    "\n\t(More details available with <comment>--help</comment>).</info>"
                );
            }
            $ignoreTempFileQuestion = new Question("Enter the name of a file or a folder to ignore: ");
            $ignoreTempFile = $helper->ask($inputInterface, $outputInterface, $ignoreTempFileQuestion);
        }

        if ($skipFlag == true && $more == false) {
            array_push($temporaryList, '');
        } else {
            array_push($temporaryList, "{$ignoreTempFile}");
        }

        return $temporaryList;
    }

    private function setAlwaysIgnoreSection(InputInterface $inputInterface, OutputInterface $outputInterface, $helper, $skipFlag, $more) {
        $alwaysList = array("README.md", ".git", ".gitignore");
        $ignoreAlwaysFile = null;
        if ($skipFlag == false) {
            if ($more == false) {
                $outputInterface->writeln(
                    "\n<info>Always section is where you want to add file or folder names that you \nnever want to be included in package. </info>" .
                    "\n<info>For convenience, <comment>.git</comment>, <comment>.gitignore</comment>, and <comment>README.md</comment> are auto included.</info>"
                );
            }

            $ignoreAlwaysFileQuestion = new Question("Enter the name of a file or folder to always ignore: ");
            $ignoreAlwaysFile = $helper->ask($inputInterface, $outputInterface, $ignoreAlwaysFileQuestion);
        }


        if ($ignoreAlwaysFile != null) {
            array_push($alwaysList, "{$ignoreAlwaysFile}");
        }

        return $alwaysList;
    }

    private function generateProjectsYamlFile($projectName, $params) {
        $yaml = Yaml::dump(array($projectName => $params), 4, 4);
        file_put_contents(__DIR__ . "/../../../config/projects.yml", $yaml, FILE_APPEND | LOCK_EX);
    }

    private function createInternalsYamlFile($params) {
        $yaml = Yaml::dump($params);
        file_put_contents(__DIR__ . "/../../../config/internals.yml", $yaml);
    }
}