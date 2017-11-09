<?php
/**
 * This command will install Quicksilver examples to a local
 * working copy of a Pantheon site.
 *
 * See README.md for usage information.
 */

namespace Pantheon\TerminusQuicksilver\Commands;

use Symfony\Component\Finder\Finder;
use Symfony\Component\Yaml\Yaml;
use Pantheon\Terminus\Commands\TerminusCommand;
use Pantheon\TerminusQuicksilver\Util\Config;
use Pantheon\TerminusQuicksilver\Util\LocalSite;

/**
 * Install Quicksilver operations from the Pantheon examples, or a personal working repository.
 */
class QuicksilverCommand extends TerminusCommand
{
    protected $quicksilverConfig;

    /**
     * Object constructor
     */
    public function __construct()
    {
      parent::__construct();
    }

    /**
     * Return our configuration object.
     */
    protected function qsConfig()
    {
      if (!isset($this->quicksilverConfig)) {
        $this->quicksilverConfig = new Config($this->log());
      }
      return $this->quicksilverConfig;
    }

    /**
     * Initialize Quicksilver, but do not install any operations
     *
     * @command quicksilver:init
     * @aliases qs:init
     */
    public function init()
    {
        $cwd = getcwd();
        $localSite = new LocalSite($cwd);
        $pantheonYmlContents = $localSite->getPantheonYml();
        if (empty($pantheonYmlContents['workflows'])) {
          $pantheonYmlContents['workflows'] = [];
        }
        $localSite->writePantheonYml($pantheonYmlContents);
        $this->log()->notice('Wrote pantheon.yml file.');
    }

    /**
     * Install everything from a profile.
     *
     * @command quicksilver:profile
     * @aliases qs:profile
     * @param string $profile
     *
     */
    public function profile($profile) {
        $cwd = getcwd();
        $localSite = new LocalSite($cwd);
        $qsExamples = $this->prepareExamples($localSite);
        if (!$qsExamples) {
          return;
        }

        $profiles = $this->qsConfig()->profiles();
        if (!isset($profiles[$profile])) {
            $this->log()->error('There is no profile named {profile}.', ['profile' => $profile]);
            return;
        }
        $installationSet = $profiles[$profile];
        $this->log()->notice('Installing: ' . json_encode($installationSet));

        foreach ($installationSet as $installProject) {
            $this->doInstall($installProject, $localSite, $qsExamples);
        }
    }

    /**
     * Set up a quicksilver operation.
     *
     * @command quicksilver:install
     * @aliases qs:install
     * @param string $project
     */
    public function install($project)
    {
        $cwd = getcwd();
        $localSite = new LocalSite($cwd);
        $qsExamples = $this->prepareExamples($localSite);
        if (!$qsExamples) {
          return;
        }
        return $this->doInstall($project, $localSite, $qsExamples);
    }

    /**
     * Run an 'install' operation on one project.
     */
    protected function doInstall($requestedProject, $localSite, $qsExamples) {
        list($majorVersion, $siteType) = $localSite->determineSiteType();
        $qsScripts = "private/scripts";
        $qsYml = "pantheon.yml";

        @mkdir(dirname($localSite->getDocRootPath() . "/" . $qsScripts));
        @mkdir($localSite->getDocRootPath() . "/" . $qsScripts);

        // Load the pantheon.yml file
        $pantheonYml = $localSite->getPantheonYml();
        $changed = false;

        // Copy the requested example into the current site
        $availableProjects = Finder::create()->directories()->in($qsExamples);
        $candidates = [];
        foreach ($availableProjects as $project) {
            if (strpos($project, $requestedProject) !== FALSE) {
                $candidates[] = $project;
            }
        }

        // Exit if there are no matches.
        if (empty($candidates)) {
            $this->log()->notice("Could not find project $requestedProject.");
            return;
        }
/*
        // If there are multipe potential matches, ask which one to install.
        if (count($candidates) > 1) {

        }
*/
        // Copy the project to the installation location
        $projectToInstall = (string) array_pop($candidates);
        $projectToInstallName = basename($projectToInstall);
        $installLocation = $qsScripts . "/" . $projectToInstallName;
        $this->log()->notice("Copy $projectToInstallName to $installLocation.");

        // Copy the project directory
        static::recursiveCopy($projectToInstall, $localSite->getDocRootPath() . "/" . $installLocation);

        // Read the README file, if there is one
        $readme = $projectToInstall . '/README.md';
        if (file_exists($readme)) {
            $readmeContents = file_get_contents($readme);
            // Look for embedded quicksilver.yml examples in the README
            preg_match_all('/```yaml([^`]*)```/', $readmeContents, $matches, PREG_PATTERN_ORDER);
            $pantheonYmlExample = static::findExamplePantheonYml($matches[1]);
        }

        // If the README does not have an example, make one up
        if (empty($pantheonYmlExample)) {
            $pantheonYmlExample =
            [
                'workflows' =>
                [
                    'deploy' =>
                    [
                        'before' =>
                        [
                            [
                                'type' => 'webphp',
                                'description' => 'Describe task here.',
                            ],
                        ]
                    ],
                ]
            ];
        }

        $availableProjects = Finder::create()->files()->name("*.php")->in($localSite->getDocRootPath() . "/" . $installLocation);
        $availableScripts = [];
        foreach ($availableProjects as $script) {
            if ($localSite->validPattern($script, $siteType, $majorVersion)) {
                $availableScripts[basename($script)] = $installLocation . "/" . $script->getRelativePathname();
            }
            else {
                unlink((string)$script);
            }
        }
        foreach ($pantheonYmlExample['workflows'] as $workflowName => $workflowData) {
            foreach ($workflowData as $phaseName => $phaseData) {
                foreach ($phaseData as $taskData) {
                    $scriptForThisExample = static::findScriptFromList(basename($taskData['script']), $availableScripts);
                    if ($scriptForThisExample) {
                        $taskData['script'] = $scriptForThisExample;
                        if (!static::hasScript($pantheonYml, $workflowName, $phaseName, $scriptForThisExample)) {
                            $pantheonYml['workflows'][$workflowName][$phaseName][] = $taskData;
                            $changed = true;
                        }
                    }
                }
            }
        }

        // Write out the pantheon.yml file again.
        if ($changed) {
            $pantheonYml = $this->fixFloats($pantheonYml);
            $pantheonYml = $localSite->writePantheonYml($pantheonYml);
            $this->log()->notice("Updated pantheon.yml.");
        }
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
     * Download the examples projects from GitHub et. al.
     * if they have not already been locally cached.
     */
    protected function prepareExamples($localSite) {
        list($majorVersion, $siteType) = $localSite->determineSiteType();
        if (!$siteType) {
            $this->log()->error("Change your working directory to a Drupal or WordPress site and run this command again.");
            return false;
        }
        $this->log()->notice("Operating on a $siteType $majorVersion site.");

        // Get the branch to operate on.
        $branch = 'master';
        if (isset($assoc_args['branch'])) {
            $branch = $assoc_args['branch'];
        }

        return $this->qsConfig()->fetchExamples();
    }

    /**
     * Search for a script containing the requested name
     * given a list of scripts (.php files) from an example project.
     */
    static protected function findScriptFromList($script, $availableScripts)
    {
        if (array_key_exists($script, $availableScripts)) {
            return $availableScripts[$script];
        }
        foreach ($availableScripts as $check => $path) {
            if (preg_match("#$script#", $check)) {
                return $path;
            }
        }
        return false;
    }

    /**
     * Search through the README, and find an example
     * pantheon.yml snippet.
     */
    static protected function findExamplePantheonYml($listOfYml)
    {
        foreach ($listOfYml as $candidate) {
            $examplePantheonYml = Yaml::parse($candidate);
            if (array_key_exists('api_version', $examplePantheonYml)) {
                return $examplePantheonYml;
            }
        }
        return [];
    }

    /**
     * Check to see if the provided pantheon.yml file
     * already has an entry for the specified script.
     */
    static protected function hasScript($pantheonYml, $workflowName, $phaseName, $script) {
        if (isset($pantheonYml['workflows'][$workflowName][$phaseName])) {
            foreach ($pantheonYml['workflows'][$workflowName][$phaseName] as $taskInfo) {
                if ($taskInfo['script'] == $script) {
                    return true;
                }
            }
        }
        return false;
    }

    static public function recursiveCopy($src,$dst) {
        $dir = opendir($src);
        @mkdir($dst);
        while(false !== ( $file = readdir($dir)) ) {
            if (( $file != '.' ) && ( $file != '..' )) {
                if ( is_dir($src . '/' . $file) ) {
                    recursiveCopy($src . '/' . $file,$dst . '/' . $file);
                }
                else {
                    copy($src . '/' . $file,$dst . '/' . $file);
                }
            }
        }
        closedir($dir);
    }
}
