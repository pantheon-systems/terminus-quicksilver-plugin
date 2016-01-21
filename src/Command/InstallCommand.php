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

        // If the examples do not exist, clone them
        $output->writeln('Fetch Quicksilver examples...');
        @mkdir($qsHome);
        @mkdir($qsExamples);
        foreach ($repositoryLocations as $name => $repo) {
            $output->writeln("Check repo $name => $repo:");
            $qsExampleDir = "$qsExamples/$name";
            if (!is_dir($qsExampleDir)) {
                $this->taskGitStack()
                    ->cloneRepo("https://github.com/pantheon-systems/quicksilver-examples.git", $qsExampleDir)
                    ->run();
            }
            else {
                chdir($qsExampleDir);
                $this->taskGitStack()
                    ->pull()
                    ->run();
                chdir($cwd);
            }
        }
        $examplePantheonYml = dirname(dirname(__DIR__)) . "/templates/example.pantheon.yml";

        // Create a "started" pantheon.yml if it does not already exist.
        if (!is_file($qsYml)) {
            $this->taskWriteToFile($qsYml)
                ->textFromFile($examplePantheonYml)
                ->run();
        }

        @mkdir(dirname($qsScripts));
        @mkdir($qsScripts);

        // Copy the requested command into the current site
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

        // Load the pantheon.yml file
        $pantheonYml = Yaml::parse($qsYml);

        $availableProjects = Finder::create()->files()->name("*.php")->in($installLocation);
        foreach ($availableProjects as $script) {

            // Fix up pantheon.yml
            // We could provide options to change these, and perhaps
            // provide defaults in an example.pantheon.yml in the
            // project directory.
            $workflow = "deploy";
            $phase = "before";
            $type = "webphp";
            $description = "Describe task here.";

            $pantheonYml['workflows'][$workflow][$phase][] =
                [
                    'type' => $type,
                    'description' => $description,
                    'script' => (string) $script,
                ];
        }

        // Write out the pantheon.yml file again.
        $pantheonYmlText = Yaml::dump($pantheonYml, PHP_INT_MAX, 2);
        $this->taskWriteToFile($qsYml)
            ->text($pantheonYmlText)
            ->run();

    }
}
