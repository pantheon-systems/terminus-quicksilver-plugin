<?php

/**
 * @file
 * Contains \Pantheon\Quicksilver\Command\AboutCommand.
 */

namespace Pantheon\Quicksilver\Util;

//use Symfony\Component\Finder\Finder;
use Symfony\Component\Yaml\Yaml;
//use Symfony\Component\Yaml\Dumper;

class LocalSite
{
    protected $dir;
    protected $docroot;

    /**
     * Initialize a reference to a local clone of a Pantheon site.
     *
     * @param string $dir     Repository root
     */
    public function __construct($dir, $docroot = false)
    {
        $this->dir = $dir;
        $this->docroot = $docroot;
    }

    public function getPantheonYmlPath()
    {
        return $this->dir . "/pantheon.yml";
    }

    public function getPantheonYml()
    {
        $qsYml = $this->getPantheonYmlPath();

        // Load the pantheon.yml file
        if (file_exists($qsYml)) {
            $pantheonYml = Yaml::parse($qsYml);
        }
        else {
            $examplePantheonYml = dirname(__DIR__) . "/templates/example.pantheon.yml";
            $pantheonYml = Yaml::parse($examplePantheonYml);
        }
        return $pantheonYml;
    }

    public function writePantheonYml($pantheonYml)
    {
        $qsYml = $this->getPantheonYmlPath();
        $pantheonYmlText = Yaml::dump($pantheonYml, PHP_INT_MAX, 2);
        return file_put_contents($qsYml, $pantheonYmlText);
    }

    public function getDocroot()
    {
        if (!$this->docroot) {
            $this->docroot = '';
            $pantheonYml = $this->getPantheonYml();
            if (isset($pantheonYml['environment']['DOCROOT'])) {
                $this->docroot = $pantheonYml['environment']['DOCROOT'];
            }
        }
        return $this->docroot;
    }

    public function getDocRootPath()
    {
        $docroot = $this->getDocroot();
        return empty(trim($docroot, '/')) ? $this->dir : $this->dir . '/' . $docroot;
    }

    public function writeDocRoot($newDocroot)
    {
        if (strpos($newDocroot, '/')) {
            if (realpath($newDocroot) == realpath($this->dir) || ($newDocroot == '/')) {
                $newDocroot = '';
            }
            elseif (dirname($newDocroot) == $this->dir) {
                $newDocroot = basename($newDocroot);
            }
            else {
                throw new Exception("Document root must be immediately inside the repository root.");
            }
        }
        $this->docroot = $newDocroot;
        $pantheonYml = $this->getPantheonYml();
        $pantheonYml['environment']['DOCROOT'] = $this->docroot;
        $this->writePantheonYml($pantheonYml);
    }

    /**
     * Look at filename patterns around the provided
     * docroot, and determine whether this looks like
     * a Drupal site or a WordPress site.
     */
    public function determineSiteType()
    {
        $root = $this->getDocRootPath();

        // If we see any of these patterns, we know the
        // framework type
        $frameworkPatterns =
        [
            'wordpress' =>
            [
                4 => 'wp-content',
            ],
            'drupal' =>
            [
                8 => 'core/misc/drupal.js',
                7 => 'misc/druplicon.png',
                6 => 'misc/drupal.js',
            ],
        ];

        foreach ($frameworkPatterns as $framework => $fileList) {
            foreach ($fileList as $majorVersion => $checkFile) {
                if (file_exists("$root/$checkFile")) {
                    return [$majorVersion, $framework];
                }
            }
        }

        return [false, false];
    }

    /**
     * Check to see if the provided script is valid for
     * the specified site type (drupal or wordpress).
     */
    public function validPattern($script, $siteType, $majorVersion, $default = true)
    {
        $scriptToCheck = basename($script);
        $filenamePatterns =
        [
            'wordpress' => ['_wp'],
            'drupal' => [],
        ];

        // Look at all of the sets of filename patterns
        foreach ($filenamePatterns as $checkType => $patternList) {
            // Consider those that are NOT of this site type
            if ($checkType != $siteType) {
                // If we can find one of the patterns in the
                // basename of the script for a set that is NOT
                // for this site type, then we have found an
                // incompatible script.
                $patternList[] = $checkType;
                foreach ($patternList as $check) {
                    if (strpos(basename($script), $check) !== FALSE) {
                        return false;
                    }
                }
            }
            // For those patterns that ARE of this site type,
            // we will check to see if the version matches the stipulate
            // major version, if any.
            else {
                $patternList[] = $checkType;
                foreach ($patternList as $check) {
                    // If the script contains the CMS name followed by its
                    // version (e.g. 'drupal8'), then it is a match.
                    if (preg_match("#{$check}{$majorVersion}[^0-9]#", $script)) {
                        return true;
                    }
                    // If the script contains the CMS name followed by any
                    // other number, then this is NOT a match.
                    if (preg_match("#{$check}[0-9]#", $script)) {
                        return false;
                    }
                }

            }
        }
        return $default;
    }

}

