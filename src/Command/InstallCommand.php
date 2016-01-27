<?php

/**
 * @file
 * Contains \Drupal\Console\Command\SecretCommand.
 */

namespace Pantheon\Quicksilver\Command;

use Robo\TaskCollection\Collection as RoboTaskCollection;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Yaml\Yaml;
use Symfony\Component\Yaml\Dumper;

class InstallCommand extends Command
{
    use \Robo\Task\FileSystem\loadTasks;
    use \Robo\Task\File\loadTasks;
    use \Robo\Task\Vcs\loadTasks;

    protected function configure()
    {
        $this
            ->setName('install')
            ->addArgument(
                'project',
                InputArgument::REQUIRED,
                'Quicksilver example project to install'
            )
            ->addOption(
               'branch',
               null,
               InputOption::VALUE_REQUIRED,
               'Branch to check out prior to installing an example (install from a PR)'
            )
            ->setDescription("Install a Pantheon example for Quicksilver");
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $output = new SymfonyStyle($input, $output);


        $application = $this->getApplication();
        // $collection = new RoboTaskCollection();

        $repositoryLocations = $application->getConfig()->get('repositories');

        $home = getenv('HOME');
        $cwd = getcwd();

        $qsHome = "$home/.quicksilver";
        $qsExamples = "$qsHome/examples";
        $qsScripts = "private/scripts";
        $qsYml = "pantheon.yml";

        // Load the pantheon.yml file
        if (file_exists($qsYml)) {
            $pantheonYml = Yaml::parse($qsYml);
        }
        else {
            $examplePantheonYml = dirname(dirname(__DIR__)) . "/templates/example.pantheon.yml";
            $pantheonYml = Yaml::parse($examplePantheonYml);
        }
        $changed = false;

        list($majorVersion, $siteType) = static::determineSiteType($cwd);
        if (!$siteType) {
            $output->writeln("Change your working directory to a Drupal or WordPress site and run this command again.");
            return false;
        }
        $output->writeln("Operating on a $siteType $majorVersion site.");

        // Get the branch to operate on.
        $branch = $input->getOption('branch');
        if (!$branch) {
            $branch = 'master';
        }

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

        @mkdir(dirname($qsScripts));
        @mkdir($qsScripts);

        // Copy the requested example into the current site
        $requestedProject = $input->getArgument('project');
        $availableProjects = Finder::create()->directories()->in($qsExamples);
        $candidates = [];
        foreach ($availableProjects as $project) {
            if (strpos($project, $requestedProject) !== FALSE) {
                $candidates[] = $project;
            }
        }

        // Exit if there are no matches.
        if (empty($candidates)) {
            $output->writeln("Could not find project $requestedProject.");
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
        $output->writeln("Copy $projectToInstall to $installLocation.");
        $this->taskCopyDir([$projectToInstall => $installLocation])->run();

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
            if (static::validScript($script, $siteType, $majorVersion)) {
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
            $output->writeln("Update pantheon.yml.");

            $pantheonYmlText = Yaml::dump($pantheonYml, PHP_INT_MAX, 2);
            $this->taskWriteToFile($qsYml)
                ->text($pantheonYmlText)
                ->run();
        }
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

    /**
     * Look at filename patterns around the provided
     * docroot, and determine whether this looks like
     * a Drupal site or a WordPress site.
     */
    static protected function determineSiteType($dir)
    {
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
                if (file_exists($checkFile)) {
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
    static protected function validScript($script, $siteType, $majorVersion)
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
        return true;
    }
}
