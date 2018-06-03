<?php

namespace Forikal\Library\GoogleAPI;

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\Filesystem\Filesystem;

/**
 * A Google API client
 *
 * @ignore A single class for all the services is made for easy Google API mocking in tests
 *
 * @property \Google_Service_Drive $driveService
 * @property \Google_Service_Sheets $sheetsService
 * @property \Google_Service_Slides $slidesService
 * ...and all the other services
 *
 * @author Surgie Finesse
 * @link https://developers.google.com/drive/api/v3/reference/
 * @link https://github.com/google/google-api-php-client
 */
class GoogleAPIClient
{
    /**
     * Mime type for a Google Drive folder
     */
    const MIME_TYPE_DRIVE_FOLDER = 'application/vnd.google-apps.folder';

    /**
     * Mime type for a Google Sheets document
     */
    const MIME_TYPE_GOOGLE_SPREADSHEET = 'application/vnd.google-apps.spreadsheet';

    /**
     * Mime type for a Google Slides document
     */
    const MIME_TYPE_GOOGLE_PRESENTATION = 'application/vnd.google-apps.presentation';

    /**
     * @var \Google_Client A Google API client from the SDK
     */
    protected $client;

    /**
     * @var LoggerInterface A logger to write the activity logs
     */
    protected $logger;

    /**
     * @var Filesystem Symfony filesystem helper
     */
    protected $filesystem;

    /**
     * @param \Google_Client|null $client A Google API client from the SDK. May have no config inside.
     * @param LoggerInterface|null $logger A logger to write the activity logs
     */
    public function __construct(\Google_Client $client = null, LoggerInterface $logger = null)
    {
        if ($client === null) {
            $client = new \Google_Client();
            $client->setLogger(new NullLogger());
        }

        $this->client = $client;
        $this->logger = $logger ?? new NullLogger();
        $this->filesystem = new Filesystem();
    }

    /**
     * Authenticates the client by asking user to go to the URL and paste the code given by Google
     *
     * @param string $clientSecretFile Path to the API client secret JSON file
     * @param string|null $accessTokenFile Path to the access token JSON file. Optional. The file may not exist.
     * @param string[] $scopes The list of the required authenticaton scopes,
     *     e.g. [\Google_Service_Drive::DRIVE_READONLY, \Google_Service_Sheets::SPREADSHEETS_READONLY]
     * @param callable $getAuthCode A function which asks the user for an auth code. Takes an authentication url (the
     *     user must open it in browser) and returns an auth code.
     * @param bool $forceAuthenticate If true, the user will be asked to authenticate even if the access token exist
     * @throws \Exception If something wrong
     */
    public function authenticate(
        string $clientSecretFile,
        ?string $accessTokenFile,
        array $scopes,
        callable $getAuthCode,
        bool $forceAuthenticate = false
    ) {
        $this->client->setApplicationName('Forikal Tools');
        $this->client->setScopes($scopes);
        $this->client->setAccessType('offline');
        $this->client->setRedirectUri('urn:ietf:wg:oauth:2.0:oob');

        $this->logger->info('Getting the Google API client secret from the `'.$clientSecretFile.'` file');
        $this->client->setAuthConfig($this->loadCredentialJSON($clientSecretFile));

        // Getting an access token
        if ($accessTokenFile !== null && !$forceAuthenticate && is_file($accessTokenFile)) {
            $this->logger->info('Getting the last Google API access token from the `'.$accessTokenFile.'` file');
            $this->client->setAccessToken($this->loadCredentialJSON($accessTokenFile));
        } else {
            // Getting an auth code
            $authUrl = $this->client->createAuthUrl();
            $authCode = $getAuthCode($authUrl);
            if (!is_string($authCode) || $authCode === '') {
                throw new \LogicException('The $getAuthCode function has returned a not-string or an empty string');
            }

            // Authenticating
            $this->logger->info('Sending the authentication code to Google');
            $accessToken = $this->client->fetchAccessTokenWithAuthCode($authCode);
            if (isset($accessToken['error_description'])) {
                throw new \RuntimeException('Google has declined the auth code: '.$accessToken['error_description']);
            }
            $this->logger->notice('Authenticated successfully');

            // Saving the token
            if ($accessTokenFile !== null) {
                $this->logger->info('Saving the access token to the `'.$accessTokenFile.'` file'
                    . ', so subsequent executions will not prompt for authorization');
                $this->saveCredentialJSON($accessTokenFile, $accessToken);
            }
        }

        // Refreshing the access token if required
        if ($this->client->isAccessTokenExpired()) {
            $this->logger->info('The access token is expired; refreshing the token');
            $accessToken = $this->client->fetchAccessTokenWithRefreshToken();
            if (isset($accessToken['error_description'])) {
                throw new \RuntimeException('Google has declined refreshing the token: '.$accessToken['error_description']);
            }

            if ($accessTokenFile !== null) {
                $this->logger->info('Saving the refreshed access token to the `'.$accessTokenFile.'` file');
                $this->saveCredentialJSON($accessTokenFile, $accessToken);
            }
        }

        $this->logger->info('The Google authentication is completed');
    }

    /**
     * {@inheritDoc}
     *
     * Gets Google services for the magic properties
     */
    public function __get(string $name)
    {
        if (substr($name, -7) === 'Service') {
            $service = ucfirst(substr($name, 0, -7));
            $serviceClass = 'Google_Service_'.$service;
            if (!class_exists($serviceClass)) {
                throw new \LogicException('The '.$service.' Google service doesn\'t exist');
            }

            return new $serviceClass($this->client);
        }

        throw new \LogicException('Undefined property: '.static::class.'::$'.$name);
    }

    /**
     * Reads a JSON data from a file
     *
     * @param string $file The file path
     * @return mixed The data
     * @throws \RuntimeException If something wrong
     */
    protected function loadCredentialJSON(string $file)
    {
        if (!file_exists($file)) {
            throw new \RuntimeException('The `'.$file.'` file doesn\'t exist');
        }
        if (!is_file($file)) {
            throw new \RuntimeException('`'.$file.'` file not a file');
        }
        if (!is_readable($file)) {
            throw new \RuntimeException('The `'.$file.'` file is not readable');
        }

        $content = file_get_contents($file);
        $data = json_decode($content, true);

        if ($data === null) {
            throw new \RuntimeException('The `'.$file.'` file content is not a valid JSON');
        }

        return $data;
    }

    /**
     * Writes a data to a JSON file
     *
     * @param string $file The file
     * @param mixed $data The data
     * @throws \RuntimeException If something wrong
     */
    protected function saveCredentialJson(string $file, $data)
    {
        $content = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR | JSON_PRETTY_PRINT);
        $this->filesystem->dumpFile($file, $content);
    }
}
