<?php

namespace Nacatamal\Parser;

use Symfony\Component\Yaml\Parser;

class ConfigParser {
    private $parser;
    private $configYml;

    public function __construct() {
        $this->parser = new Parser();
        $this->configYml = $this->parser->parse(file_get_contents(__DIR__ . "/../../../config/config.yml"));
    }

    public function getProjects() {
        $grab = array();

        foreach ($this->configYml as $projects => $configurations) {
            if ($projects == "projects") {
                array_push($grab, $configurations);
            }
        }

        return $grab;
    }

    public function getProjectParams($project) {
        $projectParams = "";
        $projects = $this->getProjects();
        foreach ($projects as $main) {
            foreach ($main as $projectName => $params) {
                if ($projectName == $project) {
                    $projectParams = $params;
                }
            }
        }

        return $projectParams;
    }

    public function getIgnoreParams($project, $allowPass) {
        $alwaysFiles = "";
        $excludeFiles = "";
        foreach ($this->configYml as $ignoreKey => $projects) {
            if ($ignoreKey == "ignore") {
                foreach ($projects as $projectName => $filesInIt) {
                    if ($projectName == $project) {
                        foreach ($filesInIt as $key => $value) {
                            if ($key == "always") {
                                $alwaysFiles = $value;
                            } else if ($key == "exclude") {
                                $excludeFiles = $value;
                            }
                        }
                    }
                }
            }
        }

        if (isset($allowPass) && $allowPass) {
            $ignoreFiles = $alwaysFiles;
        } else {
            $ignoreFiles = array_merge($alwaysFiles, $excludeFiles);
        }

        return $ignoreFiles;
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

    public function getPostDeployParams($project) {
        foreach ($this->configYml as $postDeployKey => $configurations) {
            if ($postDeployKey == "post_deploy") {
                foreach ($configurations as $projectName => $values) {
                    if ($projectName == $project) {
                        $projectConfigs = array(
                            $projectName => $values
                        );
                        return $projectConfigs;
                    }
                }
            }
        }
    }

    public function getDefaults() {
        foreach ($this->configYml as $defaults => $settings) {
            if ($defaults == "defaults") {
                return $settings;
            }
        }
    }
}