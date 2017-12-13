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

    /**
     * Get the server's user, ip, port and the directory path to send the package to
     *
     * @param $projectWanted
     * @param $serverWanted
     * @return mixed
     */
    public function getDeployTo($projectWanted, $serverWanted) {
        foreach ($this->configYml as $projectName => $projectDeployToConf) {
            if ($projectName == $projectWanted) {
                return $projectDeployToConf['deploy_to'][$serverWanted];
            }
        }
        return false;
    }

    /**
     * Gets the command that needs to be executed before deploy.
     * e.g. /tmp/script.sh param1 param2
     *
     * @param $project - name of the project in project.yml
     * @return string - a string such as '/usr/bin/php /tmp/script.php'
     */
    public function getPrePackageRuntimeScript($project) {
        return $this->configYml[$project]['runtime_scripts']['pre_package'];
    }

    /**
     * Gets the command that needs to be executed post deploy.
     * e.g. /tmp/script.sh param1 param2
     *
     * @param $project - name of the project in project.yml
     * @return mixed - a string such as '/usr/bin/php /tmp/script.php'
     */
    public function getPostDeployRuntimeScript($project) {
        return $this->configYml[$project]['runtime_scripts']['post_deploy'];
    }

    public function getDefaults() {
        return $this->internalsYml;
    }

    public function getPassword($project) {
        return $this->configYml[$project]['password'];
    }
}