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
} 