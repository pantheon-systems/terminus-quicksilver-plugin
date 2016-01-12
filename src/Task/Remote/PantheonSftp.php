<?php
namespace Pantheon\Quicksilver\Task\Remote;

use Terminus;
use Terminus\Auth;
use Terminus\Request;
use Terminus\Utils;
use Terminus\Commands\TerminusCommand;
use Terminus\Helpers\Input;
use Terminus\Exceptions\TerminusException;
use Terminus\Models\User;
use Terminus\Models\Workflow;
use Terminus\Models\Collections\Sites;

use Robo\Contract\CommandInterface;
use Robo\Task\BaseTask;
use Robo\Task\Remote;
use Robo\Exception\TaskException;

/**
 * Executes sftp to Pantheon in a flexible manner.
 */
class PantheonSftp extends BaseTask implements CommandInterface
{
    protected $sftpCommand = false;

    protected $sites;

    protected $site;

    protected $env;

    protected $ops = [];

    public static function init()
    {
        return new static();
    }

    public function __construct()
    {
        Auth::ensureLogin();
        parent::__construct();
        $this->sites = new Sites();
    }

    public function site($sitename) {
        $this->site = $site;
    }

    public function env($envname) {
        $this->envId = ?;
    }

    public function envId($envId) {
        $this->envId = $envId;
    }

    protected function getSftpCommand($site, $env) {
        $environment = $site->environments->get($env_id);
        $info        = $environment->connectionInfo();

        if (!$this->$sftpCommand) {
            // Get sftp command for site and env
            $cmd = "terminus site connection-info --site=$site --env=$env --field=sftp_command";
            $this->$sftpCommand = `$cmd`;
        }
        return $this->$sftpCommand;
    }

    public function get($file) {
        $ops[__FUNCTION__] = $file;
        return $this;
    }

    public function mkdir($dir) {
        $ops[__FUNCTION__] = $dir;
        return $this;
    }

    public function cd($dir) {
        $ops[__FUNCTION__] = $dir;
        return $this;
    }

    public function mcd($dir) {
        $this->mkdir($dir);
        $this->cd($dir);
        return $this;
    }

    public function put($file) {
        $ops[__FUNCTION__] = $file;
        return $this;
    }

    /**
     * This can either be a full rsync path spec (user@host:path) or just a path.
     * In case of the former do not specify host and user.
     *
     * @param string $path
     * @return $this
     */
    public function fromPath($path)
    {
        $this->fromPath = $path;

        return $this;
    }

    /**
     * This can either be a full rsync path spec (user@host:path) or just a path.
     * In case of the former do not specify host and user.
     *
     * @param string $path
     * @return $this
     */
    public function toPath($path)
    {
        $this->toPath = $path;

        return $this;
    }

    public function progress()
    {
        $this->option(__FUNCTION__);

        return $this;
    }

    public function stats()
    {
        $this->option(__FUNCTION__);

        return $this;
    }

    public function recursive()
    {
        $this->option(__FUNCTION__);

        return $this;
    }

    public function verbose()
    {
        $this->option(__FUNCTION__);

        return $this;
    }

    public function checksum()
    {
        $this->option(__FUNCTION__);

        return $this;
    }

    public function archive()
    {
        $this->option(__FUNCTION__);

        return $this;
    }

    public function compress()
    {
        $this->option(__FUNCTION__);

        return $this;
    }

    public function owner()
    {
        $this->option(__FUNCTION__);

        return $this;
    }

    public function group()
    {
        $this->option(__FUNCTION__);

        return $this;
    }

    public function times()
    {
        $this->option(__FUNCTION__);

        return $this;
    }

    public function delete()
    {
        $this->option(__FUNCTION__);

        return $this;
    }

    public function timeout($seconds)
    {
        $this->option(__FUNCTION__, $seconds);

        return $this;
    }

    public function humanReadable()
    {
        $this->option('human-readable');

        return $this;
    }

    public function wholeFile()
    {
        $this->option('whole-file');

        return $this;
    }

    public function dryRun()
    {
        $this->option('dry-run');

        return $this;
    }

    public function itemizeChanges()
    {
        $this->option('itemize-changes');

        return $this;
    }

    /**
     * Excludes .git/, .svn/ and .hg/ folders.
     *
     * @return $this
     */
    public function excludeVcs()
    {
        $this->exclude('.git/')
            ->exclude('.svn/')
            ->exclude('.hg/');

        return $this;
    }

    public function exclude($pattern)
    {
        return $this->option('exclude', escapeshellarg($pattern));
    }

    public function excludeFrom($file)
    {
        if (!is_readable($file)) {
            throw new TaskException($this, "Exclude file $file is not readable");
        }

        return $this->option('exclude-from', $file);
    }

    public function filesFrom($file)
    {
        if (!is_readable($file)) {
            throw new TaskException($this, "Files-from file $file is not readable");
        }

        return $this->option('files-from', $file);
    }

    public function remoteShell($command)
    {
        $this->option('rsh', "'$command'");

        return $this;
    }

    /**
     * @return \Robo\Result
     */
    public function run()
    {
        $command = $this->getCommand();
        $this->printTaskInfo("Running <info>{$command}</info>");

        return $this->executeCommand($command);
    }

    /**
     * Returns command that can be executed.
     * This method is used to pass generated command from one task to another.
     *
     * @return string
     */
    public function getCommand()
    {
        $this->option(null, $this->getPathSpec('from'))
            ->option(null, $this->getPathSpec('to'));

        return $this->command . $this->arguments;
    }

    protected function getPathSpec($type)
    {
        if ($type !== 'from' && $type !== 'to') {
            throw new TaskException($this, 'Type must be "from" or "to".');
        }
        foreach (['host', 'user', 'path'] as $part) {
            $varName = $type . ucfirst($part);
            $$part = $this->$varName;
        }
        $spec = isset($path) ? $path : '';
        if (!empty($host)) {
            $spec = "{$host}:{$spec}";
        }
        if (!empty($user)) {
            $spec = "{$user}@{$spec}";
        }

        return $spec;
    }
}
