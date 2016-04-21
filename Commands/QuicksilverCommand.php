<?php
/**
 * This command will install Quicksilver examples to a local
 * working copy of a Pantheon site.
 *
 * See README.md for usage information.
 */

namespace Terminus\Commands;

use Terminus\Commands\TerminusCommand;
use Terminus\Session;

use Symfony\Component\Finder\Finder;
use Symfony\Component\Yaml\Yaml;
use Pantheon\Quicksilver\Util\Config;
use Pantheon\Quicksilver\Util\LocalSite;

/**
 * Install Quicksilver operations from the Pantheon examples, or a personal working repository.
 *
 * @command quicksilver
 */
class QuicksilverCommand extends TerminusCommand {
    protected $config;

    /**
     * Object constructor
     *
     * @param array $options Options to construct the command object
     * @return SiteCommand
     */
    public function __construct(array $options = []) {
      parent::__construct($options);

      $this->loadUtil('LocalSite');
      $this->loadUtil('Config');
    }

    protected function config() {
      if (!isset($this->config)) {
        $this->config = new Config($this->log());
      }
      return $this->config;
    }

    protected function loadUtil($utilName) {
      if (!class_exists('Pantheon\\Quicksilver\\Util\\' . $utilName)) {
        include_once(__DIR__ . "/../Util/{$utilName}.php");
      }
    }

    /**
     * Initialize Quicksilver, but do not install any operations
     */
    public function init($args, $assoc_args) {
        $cwd = getcwd();
        $localSite = new LocalSite($cwd);
        $pantheonYmlContents = $localSite->getPantheonYml();
        if (empty($pantheonYmlContents['workflows'])) {
          $pantheonYmlContents['workflows'] = [];
        }
        $this->log()->warning('Removing comments from pantheon.yml.');
        $localSite->writePantheonYml($pantheonYmlContents);
        $this->log()->notice('Wrote pantheon.yml file.');
    }

    /**
     * Install everything from a profile.
     *
     * TODO: Terminus has a limitation that arg values are not correctly validated.
     * 'email' is handled specially, though, so we will use that for now until
     * this issue can be addressed.  Note also that the help text for positional
     * arguments is also not shown.
     *
     *  ## OPTIONS
     * <email>
     * : The name of the section in 'profiles:' in ~/.quicksilver/quicksilver.yml to install.
     */
    public function profile($args, $assoc_args) {
        $cwd = getcwd();
        $localSite = new LocalSite($cwd);
        $qsExamples = $this->prepareExamples($localSite);
        if (!$qsExamples) {
          return;
        }

        $profiles = $this->config()->profiles();
        $requestedProfile = array_shift($args);
        if (!isset($profiles[$requestedProfile])) {
            $this->log()->error('There is no profile named {profile}.', ['profile' => $requestedProfile]);
            return;
        }
        $installationSet = $profiles[$requestedProfile];
        $this->log()->notice('Installing: ' . json_encode($installationSet));

        foreach ($installationSet as $installProject) {
            $this->doInstall($installProject, $localSite, $qsExamples);
        }
    }

    /**
     * Set up a quicksilver operation.
     *
     * TODO: Terminus has a limitation that arg values are not correctly validated.
     * 'email' is handled specially, though, so we will use that for now until
     * this issue can be addressed.  Note also that the help text for positional
     * arguments is also not shown.
     *
     *  ## OPTIONS
     * <email>
     * : The example quicksilver project to install
     *
     * [--branch=<branch>]
     * : The branch / multidev environment to work on. Default is master.
     */
    public function install($args, $assoc_args) {
        $requestedProject = array_shift($args);
        $cwd = getcwd();
        $localSite = new LocalSite($cwd);
        $qsExamples = $this->prepareExamples($localSite);
        if (!$qsExamples) {
          return;
        }
        return $this->doInstall($requestedProject, $localSite, $qsExamples);
    }

    protected function doInstall($requestedProject, $localSite, $qsExamples) {
        $cwd = getcwd();
        list($majorVersion, $siteType) = $localSite->determineSiteType($cwd);
        $qsScripts = "private/scripts";
        $qsYml = "pantheon.yml";
        
        @mkdir(dirname($qsScripts));
        @mkdir($qsScripts);

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
        $installLocation = "$qsScripts/" . basename($projectToInstall);
        $this->log()->notice("Copy $projectToInstall to $installLocation.");

        // Copy the project directory
        static::recursiveCopy($projectToInstall, $installLocation);

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

        $availableProjects = Finder::create()->files()->name("*.php")->in($installLocation);
        $availableScripts = [];
        foreach ($availableProjects as $script) {
            if ($localSite->validPattern($script, $siteType, $majorVersion)) {
                $availableScripts[basename($script)] = (string)$script;
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
            $this->log()->warning('Removing comments from pantheon.yml.');
            $pantheonYml = $localSite->writePantheonYml($pantheonYml);
            $this->log()->notice("Updated pantheon.yml.");
        }
    }

    protected function prepareExamples($localSite) {
        list($majorVersion, $siteType) = $localSite->determineSiteType($cwd);
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

        return $this->config()->fetchExamples();
    }

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
