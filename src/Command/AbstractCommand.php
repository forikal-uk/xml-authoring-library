<?php

namespace XmlSquad\Library\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\ConsoleOutputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Exception\FileNotFoundException;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Yaml\Parser;

/**
 * Class AbstractCommand
 */
abstract class AbstractCommand extends Command
{
    const DEFAULT_CONFIG_FILENAME = 'XmlAuthoringProjectSettings.yaml';

    /**
     * @string Appended to directories to go up a level.
     */
    const SHIFT_DIR_PARENT = DIRECTORY_SEPARATOR . '..';

    /**
     * @var Filesystem
     */
    protected $filesystem;

    /**
     * AbstractXmlSquadCommand constructor.
     * @param null $name
     * @param Filesystem|null $filesystem
     */
    public function __construct($name = null, Filesystem $filesystem = null)
    {
        parent::__construct($name);

        $this->filesystem = $filesystem ?? new Filesystem();
    }


    /**
     * Configure GApiConnectionOption - [gApiOAuthSecretFile]
     *
     * @param int $mode
     * @param string $description
     * @return $this
     */
    protected function configureGApiOAuthSecretFileOption(
        $mode = InputOption::VALUE_OPTIONAL,
        $description = 'The path to an application client secret file used for authentication to Google.')
    {
        $this
            ->addOption(
                'gApiOAuthSecretFile',
                null,
                $mode,
                $description
            );
        return $this;
    }


    /**
     * Get GApiConnectionOption - [gApiOAuthSecretFile]
     *
     * @param InputInterface $input
     * @return mixed
     */
    protected function getGApiOAuthSecretFileOption(InputInterface $input) {
        return $input->getOption('gApiOAuthSecretFile');
    }



    /**
     * Configure GApiConnectionOption - [gApiAccessTokenFile]
     *
     * @param int $mode
     * @param string $description
     * @return $this
     */
    protected function configureGApiAccessTokenFileOption(
        $description = 'The path to an access token file. The'
        . ' file may not exists. If an access token file is used, the command remembers user credentials and'
        . ' doesn\'t require a Google authentication next time.')
    {

        $this
            ->addOption(
                'gApiAccessTokenFile',
                't',
                InputOption::VALUE_OPTIONAL,
                $description
            );
        return $this;
    }


    /**
     * Get GApiConnectionOption - [gApiAccessTokenFile]
     *
     * @param InputInterface $input
     * @return mixed
     */
    protected function getGApiAccessTokenFileOption(InputInterface $input) {
        return $input->getOption('gApiAccessTokenFile');
    }

    /**
     * Configure GApiConnectionOption - [gApiOAuthSecretFile]
     *
     * @param int $mode
     * @param string $description
     * @return $this
     */
    protected function configureGApiServiceAccountCredentialsFileOption(
        $description = 'Path to the .json file with Google user credentials.',
        $default = null,
        $mode = InputOption::VALUE_OPTIONAL)
    {
        $this
            ->addOption(
                'gApiServiceAccountCredentialsFile',
                'c',
                $mode,
                $description,
                $default);
        return $this;
    }

    /**
     * Configure GApiConnectionOption - [forceAuthenticate]
     *
     * @param int $mode
     * @param string $description
     * @return $this
     */
    protected function configureForceAuthenticateOption(
        $description = 'If set, you will be asked to authenticate even if an access token exist.')
    {

        $this
            ->addOption(
                'forceAuthenticate',
                null,
                InputOption::VALUE_NONE,
                $description
            );
        return $this;
    }


    /**
     * Get GApiConnectionOption [forceAuthenticate]
     *
     * @param InputInterface $input
     * @return mixed
     */
    protected function getForceAuthenticateOption(InputInterface $input){
        return $input->getOption('forceAuthenticate');
    }


    /**
     * Get GApiConnectionOption - [gApiServiceAccountCredentialsFile]
     *
     * @param InputInterface $input
     * @return mixed
     */
    protected function getGApiServiceAccountCredentialsFileOption(InputInterface $input) {
        return $input->getOption('gApiServiceAccountCredentialsFile');
    }




    /**
     * Configure the [driveUrl] argument.
     *
     * @param string $description
     * @return $this
     */
    protected function doConfigureDriveUrlArgument(
        $description = 'The URL of the Google Drive entity (Google Sheet or Google Drive folder).')
    {

        $this
            ->addArgument(
                'driveUrl',
                InputArgument::REQUIRED,
                $description
            );

        return $this;
    }


    /**
     * @param $configFilename
     * @return array
     */
    protected function getConfigOptions($configFilename)
    {
        $configFilename = $this->getConfigFilename($configFilename);
        $parser = new Parser();
        return $parser->parseFile($configFilename);
    }

    /**
     * Optional argument that specifies default
     *
     * @return $this
     */
    protected function doConfigureConfigFilename()
    {
        $this
            ->addOption('configFilename', 'c', InputOption::VALUE_OPTIONAL, 'Name of configuration file', self::DEFAULT_CONFIG_FILENAME);
        return $this;
    }


    /**
     * Get DataSourceOption [driveUrl]
     *
     * @param InputInterface $input
     * @return mixed
     */
    protected function getDriveUrlArgument(InputInterface $input){
        return $input->getArgument('driveUrl');
    }

    /**
     * @param string $configFilename
     * @return string
     * @throws FileNotFoundException
     */
    protected function getConfigFilename($configFilename)
    {
        $cwd = getcwd();
        $shift = '';
        do {
            $configDirectory = realpath($cwd . $shift);
            $configFilepath = rtrim($configDirectory, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $configFilename;
            if ($this->filesystem->exists($configFilepath)) {
                return $configFilepath;
            }
            $shift .= self::SHIFT_DIR_PARENT;
        } while ($this->isReadableDirectory($configDirectory));

        throw new FileNotFoundException(sprintf(
            'Configuration file not found'. $this->getOpenBaseDirWarning() . '.'
        ));
    }

    /**
     * Returns true if the directory is readable whilst suppressing errors caused by open_basedir settings.
     *
     * @param string $directory
     * @return bool
     */
    protected function isReadableDirectory($directory)
    {
        return (@is_readable(@realpath($directory . self::SHIFT_DIR_PARENT)));
    }

    /**
     * Returns a supplamentary message about open base directories if open_basedir has a value.
     *
     * @return string
     */
    private function getOpenBaseDirWarning()
    {
        if ($this->getOpenBaseDir()) {
            return ' when searching all directories that are open ['.$this->getOpenBaseDir().']';
        }
    }

    /**
     * Get the value of the open_basedir ini setting.
     *
     * @return string
     */
    private function getOpenBaseDir()
    {
        return ini_get('open_basedir');
    }


    /**
     * Prints an error to an output
     *
     * @param OutputInterface $output
     * @param string $message The error message
     */
    protected function writeError(OutputInterface $output, string $message)
    {
        if ($output instanceof ConsoleOutputInterface) {
            $output = $output->getErrorOutput();
        }

        $output->writeln($this->getHelper('formatter')->formatBlock($message, 'error'));
    }
}
