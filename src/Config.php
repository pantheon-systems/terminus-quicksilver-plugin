<?php

/**
 * @file
 * Contains \Drupal\Console\Config.
 */

namespace Pantheon\Quicksilver;

use Symfony\Component\Yaml\Parser;

/**
 * Class Config
 * @package Pantheon\Quicksilver
 */
class Config
{
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
