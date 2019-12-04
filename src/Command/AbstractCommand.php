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
use Symfony\Component\Yaml;
use XmlSquad\Library\GoogleAPI\GoogleClientFactory;
use XmlSquad\Library\GoogleAPI\GoogleAPIClient;
use Google_Service_Drive;
use Google_Service_Sheets;

use XmlSquad\Library\Console\ConsoleLogger;
use Psr\Log\LogLevel;

/**
 * Class AbstractCommand
 */
abstract class AbstractCommand extends Command
{

    /***
     * After we have analysed which Google API Authentication methods are possible, and noted the prefered auth type.
     * This constant represents the conclusion and defines the actual method to use.
     */
    const GOOGLE_API_KEYAUTHTYPECONLUSION__SERVICEKEY = 'GOOGLE_API_KEYAUTHTYPECONLUSION__SERVICEKEY';
    const GOOGLE_API_KEYAUTHTYPECONLUSION__OAUTHKEY = 'GOOGLE_API_KEYAUTHTYPECONLUSION__OAUTHKEY';

    /**
     * The name of fallback configuration file for a case when the API files paths are not specified in the input
     */
    const DEFAULT_CONFIG_FILENAME = 'XmlAuthoringProjectSettings.yaml';


    /**
     * Root gApiAccess config key
     */
    const ROOT_CONFIG_G_API_ACCESS_KEY = 'gApiAccess';


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
                'c',
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
                null,//'c',
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


    protected function doConfigureDataSourceOptions()
    {
        $this
            ->doConfigureDriveUrlArgument()
            ->doConfigureDriveUrlIsRecursiveArgument();

        return $this;
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
     * Configure the [driveUrl] argument.
     *
     * @param string $description
     * @return $this
     */
    protected function doConfigureDriveUrlIsRecursiveArgument(
        $description = 'If the Google Drive entity is a Google Drive folder, this option specifies whether or not to recurse through sub-directories to find sheets.')
    {

        $this
            ->addOption(
                'recursive',
                'r',
                InputOption::VALUE_NONE,
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
     * Get DataSourceOption [recursive]
     *
     * @param InputInterface $input
     * @return mixed
     */
    protected function getIsRecursiveOption(InputInterface $input){
        return $input->getOption('recursive');
    }


    protected function makeGoogleClient($credentialsPath)
    {
        $clientFactory = new GoogleClientFactory();
        $client = $clientFactory->createClient($credentialsPath);

        return $client;
    }



    protected function makeAuthenticatedGoogleAPIClient(
        $input,
        $output,
        $fullCredentialsPath,
        $keyAuthTypeConlusion = null
    ){

        //$googleAPIClient = new GoogleAPIClient();
        $output->writeln(__METHOD__, OutputInterface::VERBOSITY_VERBOSE);

        if ($keyAuthTypeConlusion == self::GOOGLE_API_KEYAUTHTYPECONLUSION__SERVICEKEY) {
            $output->writeln("Creating googleAPIClient with Service Key ['.$fullCredentialsPath.']", OutputInterface::VERBOSITY_VERBOSE);

            $googleAPIClient = $this->googleAPIFactory->make($this->makeConsoleLogger($output), $this->makeGoogleClient($fullCredentialsPath));
        } else {

            $tokenFileValue = $this->findGApiAccessTokenFileValue($input, $output);
            $forceAuthentication = $this->getForceAuthenticateOption($input);

            $output->writeln('Creating googleAPIClient with OAuth Credentials Key['.$fullCredentialsPath.'] TokenFile['.$tokenFileValue.'] forceAuthentication['. $forceAuthentication .']', OutputInterface::VERBOSITY_VERBOSE);
            $googleAPIClient = $this->googleAPIFactory->make($this->makeConsoleLogger($output));

            $googleAPIClient->authenticateFromCommand(
                $input,
                $output,
                $fullCredentialsPath,
                //$this->fileOptionToFullPath($this->getGApiAccessTokenFileOption($input)),
                $tokenFileValue,
                [Google_Service_Drive::DRIVE_READONLY, Google_Service_Sheets::SPREADSHEETS_READONLY],
                $forceAuthentication
            );

        }




        return $googleAPIClient;
    }



    /**
     * Creates a PSR logger instance which prints messages to the command output
     *
     * @param OutputInterface $output
     * @return ConsoleLogger
     */
    protected function makeConsoleLogger(OutputInterface $output): ConsoleLogger
    {
        return new ConsoleLogger($output, [
            LogLevel::DEBUG  => OutputInterface::VERBOSITY_VERY_VERBOSE,
            LogLevel::INFO   => OutputInterface::VERBOSITY_VERBOSE,
            LogLevel::NOTICE => OutputInterface::VERBOSITY_NORMAL
        ], [
            LogLevel::DEBUG  => '',
            LogLevel::INFO   => '',
            LogLevel::NOTICE => 'info'
        ]);
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

        $output->writeln($this->getHelper('formatter')->formatBlock($message, 'error'),OutputInterface::VERBOSITY_NORMAL);
    }

    /**
     * From ping-drive command
     *
     */

    /**
     * Attempts to find GApiOAuthSecretFile from option or config.
     *
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return string|null If the input is incorrect, null is returned. Otherwise an string is returned.
     */
    protected function findGApiOAuthSecretFileValue(InputInterface $input, OutputInterface $output): ?string
    {
        $needToParseConfigFile = false;

        $gApiOAuthSecretFileOption = $this->getGApiOAuthSecretFileOption($input);
        if ($gApiOAuthSecretFileOption === null) {
            $needToParseConfigFile = true;

            $output->writeln('The GApiOAuthSecretFileOption is not specified in command options.', OutputInterface::VERBOSITY_VERBOSE);

        }

        if (!$needToParseConfigFile) {
            $output->writeln('The gApiOAuthSecretFile was specified in command options.', OutputInterface::VERBOSITY_VERBOSE);
            return $gApiOAuthSecretFileOption;
        }


        // If the API file paths are not specified, find and read a configuration file
        if ($needToParseConfigFile) {

            $output->writeln('Trying to parse config file for GApiOAuthSecretFile', OutputInterface::VERBOSITY_VERBOSE);

            if(!$this->isGApiAuthConfigOptionsFindable($output)){

                $output->writeln('GApiAuthConfigOptions are not findable.', OutputInterface::VERBOSITY_VERBOSE);
                return null;
            }
            //else
            $output->writeln('GApiAuthConfigOptions are findable.', OutputInterface::VERBOSITY_VERBOSE);


            if ($this->findGApiOAuthSecretFileConfig($output)) {
                $output->writeln('Found GApiOAuthSecretFile specified in config.', OutputInterface::VERBOSITY_VERBOSE);
                return $this->findGApiOAuthSecretFileConfig($output);
            } else {
                $output->writeln('Could not find GApiOAuthSecretFile specified in config.', OutputInterface::VERBOSITY_VERBOSE);
            }


        }

        $this->writeln('The client secret file is specified neither in the CLI arguments nor in'
            . ' the configuration file', OutputInterface::VERBOSITY_VERBOSE);
        return null;
    }


    /**
     * Attempts to find GApiAccessTokenFile from option or config.
     *
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return stirng|null If the input is incorrect, null is returned. Otherwise an string is returned.
     */
    protected function findGApiAccessTokenFileValue(InputInterface $input, OutputInterface $output): ?string
    {

        $needToParseConfigFile = false;


        $gApiAccessTokenFile = $this->getGApiAccessTokenFileOption($input);
        if ($gApiAccessTokenFile === null) {
            $needToParseConfigFile = true;

            $output->writeln('The access token file path is not specified, will try to get the path from a configuration file', OutputInterface::VERBOSITY_VERBOSE);

        }

        // If the API file paths are not specified, find and read a configuration file
        if ($needToParseConfigFile) {
            if(!$this->isGApiAuthConfigOptionsFindable($output)){
                return null;
            }

            if ($gApiAccessTokenFile === null && $this->findGApiAccessTokenFileConfig($output)) {
                $gApiAccessTokenFile = $this->findGApiAccessTokenFileConfig($output);
            }
        }

        return $gApiAccessTokenFile;
    }



    /**
     * Attempts to find config setting for [gApiAccessTokenFile].
     *
     * @param OutputInterface $output
     * @return mixed|null
     */
    protected function findGApiAccessTokenFileConfig(OutputInterface $output)
    {
        try {
            $dataFromConfigFile = $this->extractGApiAuthConfigSettings($this->getDataFromConfigFile($output), $output);
        } catch (\RuntimeException $exception) {
            $this->writeError($output, 'Couldn\'t read a configuration file: '.$exception->getMessage());
            return null;
        }


        if (isset($dataFromConfigFile['gApiAccessTokenFile'])) {
            return $dataFromConfigFile['gApiAccessTokenFile'];

        }
        //else
        return null;
    }

    /**
     * Attempts to find [gApiOAuthSecretFile] config setting.
     *
     *
     * @param OutputInterface $output
     * @return mixed|null|string
     */
    protected function findGApiOAuthSecretFileConfig(OutputInterface $output)
    {
        try {
            $dataFromConfigFile = $this->extractGApiAuthConfigSettings($this->getDataFromConfigFile($output), $output);
        } catch (\RuntimeException $exception) {
            $this->writeError($output, 'Couldn\'t read a configuration file: '.$exception->getMessage());
            return null;
        }

        if (isset($dataFromConfigFile['gApiOAuthSecretFile'])) {
            return $dataFromConfigFile['gApiOAuthSecretFile'];

        }
        //else
        return null;
    }




    protected function isGApiAuthConfigOptionsFindable(OutputInterface $output)
    {
        try {
            if ($this->extractGApiAuthConfigSettings($this->getDataFromConfigFile($output), $output)){
                return true;
            }
        } catch (\RuntimeException $exception) {
            $this->writeError($output, 'Couldn\'t read a configuration file: '.$exception->getMessage());
            return false;
        }


    }

    /**
     * Extract GApiAuth settings from array of config data.
     *
     * @param array|null $configData
     * @param OutputInterface $output
     * @return array Empty if no settings extracted.
     */
    protected function extractGApiAuthConfigSettings($configData, OutputInterface $output)
    {
        $options = [];

        if (!($configData)){
            //No config data
            return $options;
        }

        // Parsing paths
        foreach (array('gApiOAuthSecretFile', 'gApiAccessTokenFile') as $option) {
            if (!isset($configData[self::ROOT_CONFIG_G_API_ACCESS_KEY][$option])) {
                continue;
            }
            if (!is_string($path = $configData[self::ROOT_CONFIG_G_API_ACCESS_KEY][$option])) {
                $this->writeError($output, 'The ['. self::ROOT_CONFIG_G_API_ACCESS_KEY .'] ['.$option.'] option value from the configuration file is not a string');
                continue;
            }

            $options[$option] = $this->getFullPath(dirname($this->findConfigFile($output)), $path);
        }

        return $options;
    }

    /**
     * Gets options values from a configuration file
     *
     * @param OutputInterface $output
     * @return array[]|null Options values; null means that a configuration file wasn't found. The keys are:
     *  - gApiOAuthSecretFile (string|null)
     *  - gApiAccessTokenFile (string|null)
     * @throws \RuntimeException If a configuration file can't be read or parsed
     */
    protected function getDataFromConfigFile(OutputInterface $output): ?array
    {

        if ($this->findConfigFile($output) === null) {

            $output->writeln('A configuration file `'.$this->getCommandStaticConfigFilename().'`'
                    . ' exists neither in the current directory nor in any parent directory', OutputInterface::VERBOSITY_NORMAL);

            return null;
        }



        $output->writeln('Getting options from the `' . $this->findConfigFile($output) . '` configuration file', OutputInterface::VERBOSITY_DEBUG);


        try {
            $configData = Yaml\Yaml::parseFile($this->findConfigFile($output));
        } catch (Yaml\Exception\ParseException $exception) {
            throw new \RuntimeException('Couldn\'t parse the configuration file: '.$exception->getMessage());
        }

        return $configData;
    }



    /**
     * Finds a configuration file within the current working directory and its parents
     *
     * @param OutputInterface $output An output to print status
     * @return string|null The file path. Null means that a file can't be found
     * @throws \RuntimeException
     */
    protected function findConfigFile(OutputInterface $output): ?string
    {
        $directory = @getcwd();
        if ($directory === false) {
            throw new \RuntimeException('Can\'t get the working directory path. Make sure the working directory is readable.');
        }

        for ($i = 0; $i < 10000; ++$i) { // `for` protects from an infinite loop
            $file = $directory . DIRECTORY_SEPARATOR . $this->getCommandStaticConfigFilename();

            if (@is_file($file)) {
                return $file;
            }

            $parentDirectory = @realpath($directory.DIRECTORY_SEPARATOR.'..'); // Gets the parent directory path

            // Check whether the parent directory is restricted
            if ($parentDirectory === false) {

                $output->writeln('The parent directory of `'.$directory.'` is restricted (maybe by open_basedir)'
                        . ', so the search of a configuration file can\'t be proceeded', OutputInterface::VERBOSITY_NORMAL);

                break;
            }

            if ($parentDirectory === $directory) break; // Check whether the current directory is a root directory
            $directory = $parentDirectory;
        }

        return null;
    }


    /**
     * Converts a relative file path to a full path
     *
     * @param string $contextPath A directory path from where the relative path is given
     * @param string $targetPath The relative path
     * @return string The full path
     */
    protected function getFullPath($contextPath, $targetPath): string
    {
        if ($this->filesystem->isAbsolutePath($targetPath)) {
            return $targetPath;
        }

        return rtrim($contextPath, '/\\').DIRECTORY_SEPARATOR.$targetPath;
    }


    protected function getCommandStaticConfigFilename()
    {
        return static::DEFAULT_CONFIG_FILENAME;
    }




}
