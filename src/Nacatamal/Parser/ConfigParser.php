<?php

namespace Nacatamal\Parser;

use Symfony\Component\Yaml\Parser;

class ConfigParser {
    private $parser;
    private $configYml;
    private $internalsYml;

    public function __construct() {
        $this->parser = new Parser();
        $this->configYml = $this->parser->parse(file_get_contents(__DIR__ . "/../../../config/projects.yml"));
        $this->internalsYml = $this->parser->parse(file_get_contents(__DIR__ . "/../../../config/internals.yml"));
    }

    public function getListOfProjects() {
        $list = array();
        foreach ($this->configYml as $projects => $configurations) {
            array_push($list, $projects);
        }

        return $list;
    }

    public function getProjectParams($project) {
        $projectParams = "";
        foreach ($this->configYml as $projectName => $configurations) {
            if ($project == $projectName) {
                $projectParams = $configurations;
            }
        }
        return $projectParams;
    }

    public function getIgnoreParams($project, $allowPass) {
        $temporaryFiles = $this->configYml[$project]['ignore']['temporary'];
        $alwaysFiles = $this->configYml[$project]['ignore']['always'];

        if (isset($allowPass) && $allowPass) {
            return $alwaysFiles;
        } else {
            return array_merge($alwaysFiles, $temporaryFiles);
        }
    }

    public function getDeployTo($projectWanted, $serverWanted) {
        foreach ($this->configYml as $deployTo => $configurations) {
            if ($deployTo == "deploy_to") {
                foreach ($configurations as $projectName => $servers) {
                    if ($projectName == $projectWanted) {
                        foreach ($servers as $serverName => $serverParams) {
                            if ($serverName == $serverWanted) {
                                return $serverParams;
                            }
                        }
                    }
                }
            }
        }
    }

    public function getPrePackageParams($project) {
        return $this->configYml[$project]['runtime_scripts']['pre_package'];
    }

    public function getPostDeployParams($project) {
        return $this->configYml[$project]['runtime_scripts']['post_deploy'];
    }

    public function getDefaults() {
        return $this->internalsYml;
    }

    public function getPassword($project) {
        return $this->configYml[$project]['password'];
    }
}