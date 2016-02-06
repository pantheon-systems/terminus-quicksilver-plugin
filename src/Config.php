<?php

/**
 * @file
 * Contains \Drupal\Console\Config.
 */

namespace Pantheon\Quicksilver;

use Symfony\Component\Yaml\Parser;
use Robo\TaskCollection\Collection as RoboTaskCollection;

/**
 * Class Config
 * @package Pantheon\Quicksilver
 */
class Config
{
    use \Robo\Task\FileSystem\loadTasks;
    use \Robo\Task\File\loadTasks;
    use \Robo\Task\Vcs\loadTasks;

    /**
     * @var array
     */
    protected $config = [];

    public function __construct()
    {
        $this->config = [];

        $applicationConfig = __DIR__ . '/../config.yml';
        $userConfig = $this->getUserHomeDir() . '/.quicksilver/quicksilver.yml';

        $this->loadFile($applicationConfig);
        $this->loadFile($userConfig);
    }

    /**
     * Clone the example scripts
     */
    public function fetchExamples($output)
    {
        $home = getenv('HOME');
        $qsHome = "$home/.quicksilver";
        $qsExamples = "$qsHome/examples";

        $repositoryLocations = $this->get('repositories');

        // If the examples do not exist, clone them
        $output->writeln('Fetch Quicksilver examples...');
        @mkdir($qsHome);
        @mkdir($qsExamples);
        foreach ($repositoryLocations as $name => $repo) {
            $output->writeln("Check branch $branch of repo $name => $repo:");
            $qsExampleDir = "$qsExamples/$name";
            if (!$repo) {
                if (is_dir($qsExampleDir)) {
                    $this->_deleteDir($qsExampleDir);
                }
            }
            elseif (!is_dir($qsExampleDir)) {
                $this->taskGitStack()
                    ->cloneRepo($repo, $qsExampleDir)
                    ->checkout($branch)
                    ->run();
            }
            else {
                $cwd = getcwd();
                chdir($qsExampleDir);

                // 'fetch' is not available in taskGitStack. Hm.
                passthru('git fetch');

                $this->taskGitStack()
                    ->checkout($branch)
                    ->pull()
                    ->run();
                chdir($cwd);
            }
        }

        return $qsExamples;
    }


    /**
     * @param $file
     * @return array
     */
    public function getFileContents($file)
    {
        if (file_exists($file)) {
            $parser = new Parser();
            return $parser->parse(file_get_contents($file));
        }

        return [];
    }

    /**
     * @param string|null $file
     * @param string|null $prefix
     *
     * @return bool
     */
    private function loadFile($file = null, $prefix=null)
    {
        $config = $this->getFileContents($file);

        if ($config) {
            if ($prefix) {
                $prefixes = explode('.', $prefix);
                $this->setResourceArray($prefixes, $this->config, $config);
            } else {
                $this->config = array_replace_recursive($this->config, $config);
            }
            return true;
        }

        return false;
    }

    /**
     * @param $parents
     * @param $parentsArray
     * @param $resource
     * @return mixed
     */
    private function setResourceArray($parents, &$parentsArray, $resource)
    {
        $ref = &$parentsArray;
        foreach ($parents as $parent) {
            $ref[$parent] = [];
            $previous = &$ref;
            $ref = &$ref[$parent];
        }

        $previous[$parent] = $resource;
        return $parentsArray;
    }

    /**
     * @param string $key
     * @param string $default
     * @return array|mixed|null|string
     */
    public function get($key, $default = '')
    {
        if (!$key) {
            return $default;
        }

        $config = $this->config;
        $items = explode('.', $key);

        if (!$items) {
            return $default;
        }
        foreach ($items as $item) {
            if (empty($config[$item])) {
                return $default;
            }
            $config = $config[$item];
        }

        return $config;
    }

    /**
     * Return the user home directory.
     *
     * @return string
     */
    public function getUserHomeDir()
    {
        return rtrim(getenv('HOME') ?: getenv('USERPROFILE'), '/\\');
    }
}
