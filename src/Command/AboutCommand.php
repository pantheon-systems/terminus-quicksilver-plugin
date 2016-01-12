<?php

/**
 * @file
 * Contains \Drupal\Console\Command\AboutCommand.
 */

namespace Pantheon\Quicksilver\Command;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Console\Command\Command;

class AboutCommand extends Command
{
    protected function configure()
    {
        $this
            ->setName('about')
            ->setDescription("About the Quicksilver CLI tool");
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $output = new SymfonyStyle($input, $output);

        $application = $this->getApplication();

        $aboutTitle = sprintf(
            '%s %s',
            $application->getName(),
            $application->getVersion()
        );

        $output->setDecorated(false);
        $output->title($aboutTitle);
        $output->setDecorated(true);

        $commands = [
            'init' => [
                "Set up a Pantheon site to use Quicksilver",
                'quicksilver init'
            ],
        ];

        foreach ($commands as $command => $commandInfo) {
            $output->writeln($commandInfo[0]);
            $output->newLine();
            $output->writeln(sprintf('  <comment>%s</comment>', $commandInfo[1]));
            $output->newLine();
        }

        $output->setDecorated(false);
        $output->section("Overview");
        $output->setDecorated(true);
        $output->writeln("Quicksilver CLI makes setting up Quicksilver quick.");
    }
}
