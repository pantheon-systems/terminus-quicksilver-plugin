<?php

/**
 * @file
 */

namespace Pantheon\TerminusQuicksilver\Util;

use Consolidation\Comments\Comments;
use Symfony\Component\Yaml\Yaml;

class LocalSite
{
    protected $dir;
    protected $docroot;
    protected $comments;

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

    /**
     * Return the path to the pantheon.yml file.
     */
    public function getPantheonYmlPath()
    {
        return $this->dir . "/pantheon.yml";
    }

    /**
     * Load the pantheon.yml file and return its parsed contents.
     */
    public function getPantheonYml()
    {
        $qsYml = $this->getPantheonYmlPath();

        // Load the pantheon.yml file
        if (file_exists($qsYml)) {
            $pantheonYmlContents = file_get_contents($qsYml);
        }
        else {
            $examplePantheonYml = __DIR__ . "/../../templates/example.pantheon.yml";
            $pantheonYmlContents = file_get_contents($examplePantheonYml);
        }
        $pantheonYml = Yaml::parse($pantheonYmlContents);
        $this->comments = new Comments();
        $this->comments->collect(explode("\n", $pantheonYmlContents));
        return $pantheonYml;
    }

    /**
     * Write a modified pantheon.yml file back to disk.
     */
    public function writePantheonYml($pantheonYml)
    {
        // Convert floats in the data to strings so that we can preserve the ".0"
        $pantheonYml = $this->fixFloats($pantheonYml);

        $qsYml = $this->getPantheonYmlPath();
        $pantheonYml = Yaml::dump($pantheonYml, PHP_INT_MAX, 2);
        $pantheonYmlLines = $this->comments->inject(explode("\n", $pantheonYml));
        $pantheonYmlText = implode("\n", $pantheonYmlLines);

        // Horrible workaround. We cannot get our yaml parser to output a
        // string such as '7.0' without wrapping it in quotes. If the data
        // type is numeric, then the yaml parser will output '7' rather than
        // '7.0'. We therefore convert floats to strings so that we
        // can retain the '.0' on the end; however, this causes the output
        // value to be wrapped in quotes, which the Pantheon schema parser
        // rejects. We therefore strip quotes from numeric types here.
        $pantheonYmlText = preg_replace("#^([^:]+: *)'([0-9.]+)'$#m", '\1\2', $pantheonYmlText);

        return file_put_contents($qsYml, $pantheonYmlText);
    }

    protected function fixFloats($data)
    {
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $data[$key] = $this->fixFloats($value);
            }
            elseif (is_float($value)) {
                $data[$key] = (string)$value;
                // Integer values would not be a float if it did not have
                // a ".0" in the source data, so put that back.
                if ($value == floor($value)) {
                    $data[$key] .= '.0';
                }
            }
        }
        return $data;
    }

    /**
     * Determine whether the document root is at the repository root,
     * or whether it is in the 'web' directory.
     */
    public function getDocroot()
    {
        if (!$this->docroot) {
            $this->docroot = '';
            $pantheonYml = $this->getPantheonYml();
            if (isset($pantheonYml['web_docroot']) && $pantheonYml['web_docroot']) {
                $this->docroot = 'web';
            }
        }
        return $this->docroot;
    }

    /**
     * Get the full path to the docroot.
     */
    public function getDocRootPath()
    {
        $docroot = $this->getDocroot();
        return empty(trim($docroot, '/')) ? $this->dir : $this->dir . '/' . $docroot;
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

