<?php
/**
 * Created by PhpStorm.
 * User: jw
 * Date: 26/06/2018
 * Time: 17:30
 */

namespace XmlSquad\Library\Command;

use Exception;
use Symfony\Component\Console\Command\Command;

use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;




use XmlSquad\Library\Application\Service\GoogleDriveProcessService;

use XmlSquad\Library\GoogleAPI\GoogleAPIClient;
use XmlSquad\Library\GoogleAPI\GoogleAPIFactory;


use XmlSquad\Library\Command\AbstractCommand;
use Symfony\Component\Filesystem\Filesystem;


/**
 * Base class for all GSheetToXml commands.
 *
 *
 * Contains common logic related to the mechanics of:
 *  defining and getting common options,
 *  accessing Google Api, reporting access errors,
 *  collecting a Google Sheet or Drive Folder of Sheets,
 *  invoking the processing of the Google Url into Xml and returning a return code.
 * 
 * Concrete classes that extend this can be responsible for:
 *  creating the classes that hold the logic relating to the particular domain model which is
 *  represented by the sheets being collected/ xml being written.
 *  adding any intermediate control steps that might be required by the particular use-case.
 * 
 *
 * @author Zoran Antolović
 * @author Johnnie Walker
 */
abstract class AbstractGSheetProcessingCommand extends AbstractCommand
{

    /**
     * If given both serviceKey and OAuth Credentials, helps choose which one to use.
     * Abstract property. Can be overidden by subclass.
     */
    protected $preferServiceKey = false;

    /**
     * {@inheritDoc}
     *
     * @param GoogleAPIFactory|null $googleAPIFactory Google API factory
     */
    public function __construct(GoogleAPIFactory $googleAPIFactory = null)
    {
        parent::__construct();

        $this->googleAPIFactory = $googleAPIFactory ?? new GoogleAPIFactory();
        $this->filesystem = new Filesystem();
    }


    /**
     * Executes the current command.
     *
     * @return null|int null or 0 if everything went fine, or an error code
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {

        if ($this->getGApiServiceAccountCredentialsFileOption($input)){
            $serviceKeyFilePathFound = true;
            $serviceKeyFilePath = $this->getGApiServiceAccountCredentialsFileOption($input);

            $output->writeln('serviceKeyFileFound ['. $serviceKeyFilePath .']', OutputInterface::VERBOSITY_VERBOSE);
        }

        if (($this->findGApiOAuthSecretFileValue($input, $output) && ($this->findGApiAccessTokenFileValue($input, $output)))) {
            $oAuthKeyAndTokenFilenameFound = true;
            $oAuthfullCredentialsPath = $this->findGApiOAuthSecretFileValue($input, $output);

            $output->writeln('oAuthKeyAndTokenFilenameFound = TRUE:', OutputInterface::VERBOSITY_VERBOSE);
            $output->writeln('oAuthfullCredentialsPath ['. $oAuthfullCredentialsPath .']', OutputInterface::VERBOSITY_VERBOSE);
            $output->writeln('GApiAccessTokenFileValue ['. $this->findGApiAccessTokenFileValue($input, $output) .']', OutputInterface::VERBOSITY_VERBOSE);

        }

        if ((!$serviceKeyFilePathFound && !$oAuthKeyAndTokenFilenameFound)) {
            throw new Exception('Neither servicekey, Oath credentials command options nor settings not found.');
            return 1;
        }

        /*
        elseif ((!$this->findGApiOAuthSecretFileValue($input, $output) || (!$this->findGApiAccessTokenFileValue($input, $output)))) {
            throw new Exception('Neither credentials command options nor settings not found.');
            return 1;
        }
        */

        //$fullCredentialsPath = $this->findFullCredentialsPath($this->getGApiOAuthSecretFileOption($input));



        if ($this->preferServiceKey && $serviceKeyFilePath){
            $fullCredentialsPath = $serviceKeyFilePath;
            $keyAuthTypeConclusion = AbstractCommand::GOOGLE_API_KEYAUTHTYPECONLUSION__SERVICEKEY;

        } else {
            $fullCredentialsPath = $oAuthfullCredentialsPath;
            $keyAuthTypeConclusion = AbstractCommand::GOOGLE_API_KEYAUTHTYPECONLUSION__OAUTHKEY;
        }

        if (!$fullCredentialsPath) {

            throw new Exception('Credentials file not found. '. PHP_EOL .' Option: ['.$this->getGApiOAuthSecretFileOption($input).']');
        }

        $output->writeln('preferServiceKey ['. ( ($this->preferServiceKey) ? 'TRUE' : 'FALSE') .']', OutputInterface::VERBOSITY_VERBOSE);
        $output->writeln('keyAuthTypeConlusion ['. $keyAuthTypeConclusion .']', OutputInterface::VERBOSITY_NORMAL);

        $output->writeln('fullCredentialsPath set to: ['. $fullCredentialsPath .']', OutputInterface::VERBOSITY_VERBOSE);

        //Delegate to the concrete class to perform the processing.
        $this->processDataSource(
            $output,
            $this->createGoogleDriveProcessService( $this->makeAuthenticatedGoogleAPIClient($input, $output, $fullCredentialsPath, $keyAuthTypeConclusion )),
            $this->getDataSourceOptions($input)
        );

        //If all went well.
        return 0;
    }


    /**
     * The concrete method that processes the Google Url.
     *
     * The interface has been kept as loose as possible to
     * allow the user-land developer freedom to inject
     * their own service class and dataSource options.
     *
     * @param OutputInterface $output
     * @param $service
     * @param $dataSourceOptions
     * @return mixed
     */
    abstract protected function processDataSource(OutputInterface $output, $service, $dataSourceOptions);


    /**
     * Configure the options that are common to most GSheetToXml commands.
     *
     * Returns $this so it can be chained with other configure methods.
     *
     * @return $this
     */
    protected function configureGSheetProcessingConsoleParameters()
    {
        $this->doConfigureDataSourceOptions();
        $this->doConfigureGApiConnectionOptions();
                //->doConfigureConfigFilename(); //@todo This is not being used by the parser, currently.

        return $this;
    }

    protected function doConfigureGApiConnectionOptions()
    {
        $this
            ->configureGApiOAuthSecretFileOption()
            ->configureGApiAccessTokenFileOption()
            ->configureForceAuthenticateOption();

        return $this;
    }






    /**
     * Factory method for GoogleDriveProcessService object.
     *
     * Method could be overriden by concrete class since
     * GoogleDriveProcessService is invoked in the
     * base class's custom processDataSource() method.
     *
     *
     * @param GoogleAPIClient $client
     * @return GoogleDriveProcessService
     */
    protected function createGoogleDriveProcessService(
        GoogleAPIClient $client){

        return new GoogleDriveProcessService($client);
    }






    /**
     * Finds the full path to the credentials file.
     *
     * @param $credentialsPathOption
     * @return string|null string if found, null if not found.
     */
    protected function findFullCredentialsPath($credentialsPathOption){

        if (!$this->isFullCredentialsPathFindable($this->fileOptionToFullPath($credentialsPathOption))){
            return NULL;
        }
        return $this->fileOptionToFullPath($credentialsPathOption);
    }

    /**
     * Determine if path is findable.
     *
     * @param $fullCredentialsPath
     * @return bool TRUE if can be found. Otherwise FALSE.
     */
    protected function isFullCredentialsPathFindable($fullCredentialsPath){
        if (false === is_file($fullCredentialsPath)){
            return FALSE;
        }
        return TRUE;
    }

    /**
     * Convert path passed as option to full path.
     *
     * We currently expect a 'path relative to working directory'
     * where the command was invoked from.
     *
     * @param $relativePath
     * @return string
     */
    protected function fileOptionToFullPath($relativePath){
        return getcwd() . '/' . ltrim($relativePath, '/');
    }




}
