<?php

/**
 * @file
 * Contains \Pantheon\Quicksilver\Command\InstallCommand.
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

use Pantheon\Quicksilver\Util\LocalSite;


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

        $cwd = getcwd();

        $qsScripts = "private/scripts";
        $qsYml = "pantheon.yml";

        $localSite = new LocalSite($cwd);
        // Load the pantheon.yml file
        $pantheonYml = $localSite->getPantheonYml();
        $changed = false;

        list($majorVersion, $siteType) = $localSite->determineSiteType($cwd);
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

        $qsExamples = $application->getConfig()->fetchExamples($output);

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
            $output->writeln("Update pantheon.yml.");
            $pantheonYml = $localSite->writePantheonYml($pantheonYml);
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

}
