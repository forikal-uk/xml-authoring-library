<?php

namespace XmlSquad\Library\GoogleAPI;

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\Console\Formatter\OutputFormatter;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;
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
     * @param string $gApiOAuthSecretFile Path to the API client secret JSON file
     * @param string|null $accessTokenFile Path to the access token JSON file. Optional. The file may not exist.
     * @param string[] $scopes The list of the required authenticaton scopes,
     *     e.g. [\Google_Service_Drive::DRIVE_READONLY, \Google_Service_Sheets::SPREADSHEETS_READONLY]
     * @param callable $getAuthCode A function which asks the user for an auth code. Takes an authentication url (the
     *     user must open it in browser) and returns an auth code.
     * @param bool $forceAuthenticate If true, the user will be asked to authenticate even if the access token exist
     * @throws \RuntimeException If the authentication failed
     * @throws \LogicException If the $getAuthCode function returns not a filled string
     */
    public function authenticate(
        string $gApiOAuthSecretFile,
        ?string $accessTokenFile,
        array $scopes,
        callable $getAuthCode,
        bool $forceAuthenticate = false
    ) {
        $this->client->setApplicationName('XmlSquad Tools');
        $this->client->setScopes($scopes);
        $this->client->setAccessType('offline');
        $this->client->setRedirectUri('urn:ietf:wg:oauth:2.0:oob');
        $this->loadClientSecretFromFile($gApiOAuthSecretFile);

        // Getting an access token
        if ($accessTokenFile !== null && !$forceAuthenticate && is_file($accessTokenFile)) {
            $this->loadAccessTokenFromFile($accessTokenFile);
        } else {
            $this->loadAccessTokenByAuthenticatingUser($getAuthCode);

            if ($accessTokenFile !== null) {
                $this->saveCurrentAccessTokenToFile($accessTokenFile);
                $this->logger->info('So subsequent executions will not prompt for authorization');
            }
        }

        if ($this->refreshAccessTokenIfRequired()) {
            if ($accessTokenFile !== null) {
                $this->saveCurrentAccessTokenToFile($accessTokenFile);
            }
        }

        $this->logger->info('The Google authentication is completed');
    }

    /**
     * A helper for a Symfony Console command. Authenticates the client by asking user to go to the URL and paste the
     * code given by Google. In case of a failed authentication prints a message to the console.
     *
     * @param InputInterface $input The command input
     * @param OutputInterface $output The command output
     * @param string $gApiOAuthSecretFile Path to the API client secret JSON file
     * @param string|null $accessTokenFile Path to the access token JSON file. Optional. The file may not exist.
     * @param string[] $scopes The list of the required authenticaton scopes,
     *     e.g. [\Google_Service_Drive::DRIVE_READONLY, \Google_Service_Sheets::SPREADSHEETS_READONLY]
     * @param bool $forceAuthenticate If true, the user will be asked to authenticate even if the access token exist
     * @return bool True if the user is authenticated successfully
     */
    public function authenticateFromCommand(
        InputInterface $input,
        OutputInterface $output,
        string $gApiOAuthSecretFile,
        ?string $accessTokenFile,
        array $scopes,
        bool $forceAuthenticate = false
    ): bool {
        try {
            $this->authenticate(
                $gApiOAuthSecretFile,
                $accessTokenFile,
                $scopes,
                function ($authURL) use ($input, $output) {
                    $output->writeln('<info>You need to authenticate to your Google account to proceed</info>');
                    $output->writeln('Open the following URL in a browser, get an auth code and paste it below:');
                    $output->writeln('');
                    $output->writeln($authURL, OutputInterface::OUTPUT_PLAIN);
                    $output->writeln('');

                    $question = new Question('Auth code: ');
                    $question->setValidator(function ($answer) {
                        $answer = trim($answer);

                        if ($answer === '') {
                            throw new \RuntimeException('Please enter an auth code');
                        }

                        return $answer;
                    });
                    return (new QuestionHelper())->ask($input, $output, $question);
                },
                $forceAuthenticate
            );
        } catch (\RuntimeException $exception) {
            $output->writeln('<error>Failed to authenticate to Google: '.OutputFormatter::escape($exception->getMessage()).'</error>');
            return false;
        }

        return true;
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
     * Loads a client secret from file to Google Client
     *
     * @param string $filePath Path to the file
     * @throws \RuntimeException If the loading failed
     */
    protected function loadClientSecretFromFile(string $filePath)
    {
        $this->logger->info('Getting the Google API client secret from the `'.$filePath.'` file');

        try {
            $secretData = $this->loadCredentialJSON($filePath);
        } catch (\RuntimeException $exception) {
            throw new \RuntimeException('Couldn\'t parse the client secret file: '.$exception->getMessage());
        }

        try {
            $this->client->setAuthConfig($secretData);
        } catch (\Exception $exception) {
            throw new \RuntimeException('Failed to apply the client secret from file `'.$filePath.'` to Google SDK: '.$exception->getMessage());
        }
    }

    /**
     * Loads an access token from file to Google Client
     *
     * @param string $filePath Path to the file
     * @throws \RuntimeException If the authentication failed
     */
    protected function loadAccessTokenFromFile(string $filePath)
    {
        $this->logger->info('Getting the last Google API access token from the `'.$filePath.'` file');

        try {
            $tokenData = $this->loadCredentialJSON($filePath);
        } catch (\RuntimeException $exception) {
            throw new \RuntimeException('Couldn\'t parse the access token file: '.$exception->getMessage());
        }

        try {
            $this->client->setAccessToken($tokenData);
        } catch (\Exception $exception) {
            throw new \RuntimeException('Failed to set the access token from file `'.$filePath.'` to Google SDK: '.$exception->getMessage());
        }
    }

    /**
     * Gets an access token using the interactive authentication process and saves it to the current Google client
     *
     * @param callable $getAuthCode A function which asks the user for an auth code. Takes an authentication url (the
     *     user must open it in browser) and returns an auth code.
     * @param string|null $tokenFilePath
     * @throws \RuntimeException If the authentication fails
     * @throws \LogicException If the $getAuthCode function returns not a filled string
     */
    protected function loadAccessTokenByAuthenticatingUser(callable $getAuthCode)
    {
        // Getting an auth code
        try {
            $authUrl = $this->client->createAuthUrl();
        } catch (\Exception $exception) {
            throw new \RuntimeException(
                'Couldn\'t create an authentication URL: '.rtrim($exception->getMessage(), '.').'. ' .
                'Maybe there is a problem with the secret file.'
            );
        }

        $authCode = $getAuthCode($authUrl);
        if (!is_string($authCode) || $authCode === '') {
            throw new \LogicException('The $getAuthCode function has returned a not-string or an empty string');
        }

        // Authenticating
        $this->logger->info('Sending the authentication code to Google');
        try {
            $accessToken = $this->client->fetchAccessTokenWithAuthCode($authCode);
        } catch (\Exception $exception) {
            throw new \RuntimeException(
                'Failed to send the authentication code to Google: '.rtrim($exception->getMessage(), '.').'. ' .
                'Maybe there is a problem with the secret file.'
            );
        }
        if (isset($accessToken['error'])) {
            throw new \RuntimeException('Google has declined the auth code: '.($accessToken['error_description'] ?? '(no message)'));
        }

        $this->logger->notice('Authenticated successfully');
    }

    /**
     * Saves the current access token to file
     *
     * @param string $tokenFilePath Path to the file
     * @throws \RuntimeException If file saving fails
     * @throws \LogicException If the client doesn't have a token
     */
    protected function saveCurrentAccessTokenToFile(string $tokenFilePath)
    {
        $this->logger->info('Saving the access token to the `'.$tokenFilePath.'` file');

        $token = $this->client->getAccessToken();
        if ($token === null) {
            throw new \LogicException('Can\'t save the token because the current Google client doesn\'t have an access token');
        }

        try {
            $this->saveCredentialJSON($tokenFilePath, $token);
        } catch (\RuntimeException $exception) {
            throw new \RuntimeException('Failed to save the access token: '.$exception->getMessage());
        }
    }

    /**
     * Refreshed the current access token (if required) and sets the new token to the Google client
     *
     * @return bool Whether the token was refreshed
     * @throws \RuntimeException If the refreshing failed
     */
    protected function refreshAccessTokenIfRequired(): bool
    {
        if (!$this->client->isAccessTokenExpired()) {
            return false;
        }

        $this->logger->info('The access token is expired; refreshing the token');

        try {
            $accessToken = $this->client->fetchAccessTokenWithRefreshToken();
        } catch (\Exception $exception) {
            throw new \RuntimeException('Failed to refresh the token: '.$exception->getMessage());
        }
        if (isset($accessToken['error'])) {
            throw new \RuntimeException('Google has declined refreshing the token: '.($accessToken['error_description'] ?? '(no message)'));
        }

        return true;
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
