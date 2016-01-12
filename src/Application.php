<?php

namespace Pantheon\Quicksilver;

use Symfony\Component\Console\Application as BaseApplication;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Debug\Debug;

/**
 * Class Application
 * @package Pantheon\Quicksilver
 */
class Application extends BaseApplication
{

    /**
     * @var string
     */
    const NAME = 'Pantheon Quicksilver CLI';
    /**
     * @var string
     */
    const VERSION = '0.1.0';
    /**
     * @var \Pantheon\Quicksilver\Config
     */
    protected $config;

    /**
     * @var string
     */
    protected $commandName;

    /**
     * Create a new application.
     *
     * @param $config
     * @param $translator
     */
    public function __construct($config)
    {
        $this->config = $config;

        parent::__construct($this::NAME, $this::VERSION);

        $this->getDefinition()->addOption(
            new InputOption('--debug', null, InputOption::VALUE_NONE, "Debug mode")
        );
    }

    /**
     * Gets the default input definition.
     *
     * @return InputDefinition An InputDefinition instance
     */
    protected function getDefaultInputDefinition()
    {
        return new InputDefinition(
            array(
            new InputArgument('command', InputArgument::REQUIRED, "Command to run"),
            new InputOption('--help', '-h', InputOption::VALUE_NONE, "Get help"),
            new InputOption('--quiet', '-q', InputOption::VALUE_NONE, "Be quiet"),
            new InputOption('--verbose', '-v|vv|vvv', InputOption::VALUE_NONE, "Be verbose"),
            new InputOption('--version', '-V', InputOption::VALUE_NONE, "Print version"),
            )
        );
    }

    /**
     * Returns the long version of the application.
     *
     * @return string The long application version
     *
     * @api
     */
    public function getLongVersion()
    {
        if ('UNKNOWN' !== $this->getName() && 'UNKNOWN' !== $this->getVersion()) {
            return sprintf("<info>%s %s</info>", $this->getName(), $this->getVersion());
        }

        return '<info>Pantheon Quicksilver CLI</info>';
    }

    /**
     * {@inheritdoc}
     */
    public function doRun(InputInterface $input, OutputInterface $output)
    {
        $output = new SymfonyStyle($input, $output);
        $config = $this->getConfig();

        if ($input) {
            $commandName = $this->getCommandName($input);
            $this->commandName = $commandName;
        }

        $debug = $input->hasParameterOption(array('--debug', ''));

        if ($debug) {
            Debug::enable();
        }

        return parent::doRun($input, $output);
    }

    /**
     * @return \Drupal\Console\Config
     */
    public function getConfig()
    {
        return $this->config;
    }

    /**
     * @param mixed $config
     */
    public function setConfig($config)
    {
        $this->config = $config;
    }
}
