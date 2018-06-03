<?php

namespace Forikal\Library\Tests\GoogleAPI;

use Forikal\Library\GoogleAPI\GoogleAPIClient;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Formatter\OutputFormatter;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Filesystem\Filesystem;

class GoogleAPIClientTest extends TestCase
{
    /**
     * Path to a directory where to store temporary test files
     */
    const TEMP_DIR = __DIR__.DIRECTORY_SEPARATOR.'temp';

    /**
     * @var Filesystem Symfony filesystem helper
     */
    protected $filesystem;

    /**
     * @var \Google_Client|\PHPUnit\Framework\MockObject\MockObject Google API client mock. The original class methods
     *     are never called
     */
    protected $googleClientMock;

    /**
     * @var LoggerInterface|\PHPUnit\Framework\MockObject\MockObject
     */
    protected $loggerMock;

    /**
     * {@inheritdoc}
     */
    protected function setUp()
    {
        parent::setUp();

        $this->filesystem = new Filesystem();
        $this->filesystem->remove(static::TEMP_DIR);
        $this->filesystem->mkdir(static::TEMP_DIR);

        $this->googleClientMock = $this
            ->getMockBuilder('Google_Client')
            ->disableOriginalConstructor()
            ->disableProxyingToOriginalMethods()
            ->getMock();
        $this->googleClientMock->method('setApplicationName')->with('Forikal Tools');
        $this->googleClientMock->method('setAccessType')->with('offline');
        $this->googleClientMock->method('setRedirectUri');

        $this->loggerMock = $this->createMock(LoggerInterface::class);
    }

    /**
     * {@inheritdoc}
     */
    protected function tearDown()
    {
        $this->filesystem->remove(static::TEMP_DIR);

        parent::tearDown();
    }

    public function testAuthenticateWithoutSecretFile()
    {
        $this->googleClientMock->method('setScopes')->with([]);

        $secretPath = static::TEMP_DIR.DIRECTORY_SEPARATOR.'secret.json';
        $this->expectException('RuntimeException');
        $this->expectExceptionMessage('The `'.$secretPath.'` file doesn\'t exist');
        $client = new GoogleAPIClient($this->googleClientMock);
        $client->authenticate($secretPath, null, [], function () {});
    }

    public function testAuthenticateWithNotExistingAccessToken()
    {
        $secretPath = static::TEMP_DIR.DIRECTORY_SEPARATOR.'secret.json';
        $tokenPath = static::TEMP_DIR.DIRECTORY_SEPARATOR.'token.json';
        file_put_contents($secretPath, '{"secret": "qwerty"}');

        $this->googleClientMock->method('setScopes')->with([\Google_Service_Drive::DRIVE_READONLY]);
        $this->googleClientMock->method('setAuthConfig')->with(['secret' => 'qwerty']);
        $this->googleClientMock->method('createAuthUrl')->willReturn('https://google.com/auth');
        $this->googleClientMock->method('fetchAccessTokenWithAuthCode')->with('foo1')->willReturn(['token' => '9892ll']);
        $this->googleClientMock->method('getAccessToken')->willReturn(['token' => '9892ll']);
        $this->googleClientMock->method('isAccessTokenExpired')->willReturn(false);

        $this->loggerMock->method('info')->withConsecutive(
            ['Getting the Google API client secret from the `'.$secretPath.'` file'],
            ['Sending the authentication code to Google'],
            ['Saving the access token to the `'.$tokenPath.'` file'],
            ['So subsequent executions will not prompt for authorization'],
            ['The Google authentication is completed']
        );
        $this->loggerMock->method('notice')->with('Authenticated successfully');

        $client = new GoogleAPIClient($this->googleClientMock, $this->loggerMock);
        $client->authenticate(
            $secretPath,
            $tokenPath,
            [\Google_Service_Drive::DRIVE_READONLY],
            function ($url) {
                $this->assertEquals('https://google.com/auth', $url);
                return 'foo1';
            }
        );
        $this->assertFileExists($tokenPath);
        $this->assertEquals(['token' => '9892ll'], json_decode(file_get_contents($tokenPath), true), 'Created JSON token file is incorrect');
    }

    public function testAuthenticateWithExistingAccessTokenAndRefresh()
    {
        $secretPath = static::TEMP_DIR.DIRECTORY_SEPARATOR.'secret.json';
        $tokenPath = static::TEMP_DIR.DIRECTORY_SEPARATOR.'token.json';
        file_put_contents($secretPath, '{"secret": "qwerty"}');
        file_put_contents($tokenPath, '{"token": ")*F)SD*&"}');

        $this->googleClientMock->method('setScopes')->with([]);
        $this->googleClientMock->method('setAuthConfig')->with(['secret' => 'qwerty']);
        $this->googleClientMock->method('setAccessToken')->with(['token' => ')*F)SD*&']);
        $this->googleClientMock->method('isAccessTokenExpired')->willReturn(true);
        $this->googleClientMock->method('fetchAccessTokenWithRefreshToken')->willReturn(['token' => '15$sDAF']);
        $this->googleClientMock->method('getAccessToken')->willReturn(['token' => '15$sDAF']);

        $this->loggerMock->method('info')->withConsecutive(
            ['Getting the Google API client secret from the `'.$secretPath.'` file'],
            ['Getting the last Google API access token from the `'.$tokenPath.'` file'],
            ['The access token is expired; refreshing the token'],
            ['Saving the access token to the `'.$tokenPath.'` file'],
            ['The Google authentication is completed']
        );

        $client = new GoogleAPIClient($this->googleClientMock, $this->loggerMock);
        $client->authenticate(
            $secretPath,
            $tokenPath,
            [],
            function () {
                $this->fail('The auth code getter function must not be called');
            }
        );
        $this->assertEquals(['token' => '15$sDAF'], json_decode(file_get_contents($tokenPath), true), 'Created JSON token file is incorrect');
    }

    public function testAuthenticateWithoutAccessToken()
    {
        $secretPath = static::TEMP_DIR.DIRECTORY_SEPARATOR.'secret.json';
        file_put_contents($secretPath, '{"secret": "qwerty"}');

        $this->googleClientMock->method('setScopes')->with([]);
        $this->googleClientMock->method('setAuthConfig')->with(['secret' => 'qwerty']);
        $this->googleClientMock->method('createAuthUrl')->willReturn('https://google.com/auth');
        $this->googleClientMock->method('fetchAccessTokenWithAuthCode')->with('foo1')->willReturn(['token' => 'mfmfmse']);
        $this->googleClientMock->method('isAccessTokenExpired')->willReturn(false);

        $this->loggerMock->method('info')->withConsecutive(
            ['Getting the Google API client secret from the `'.$secretPath.'` file'],
            ['Sending the authentication code to Google'],
            ['The Google authentication is completed']
        );
        $this->loggerMock->method('notice')->with('Authenticated successfully');

        $client = new GoogleAPIClient($this->googleClientMock, $this->loggerMock);
        $client->authenticate($secretPath, null, [], function () { return 'foo1'; });
        $this->assertCount(1, array_diff(scandir(static::TEMP_DIR), array('..', '.')));
    }

    public function testForceAuthenticate()
    {
        $secretPath = static::TEMP_DIR.DIRECTORY_SEPARATOR.'secret.json';
        $tokenPath = static::TEMP_DIR.DIRECTORY_SEPARATOR.'token.json';
        file_put_contents($secretPath, '{"secret": "qwerty"}');
        file_put_contents($tokenPath, '{"token": "U23Slsd--4"}');

        $this->googleClientMock->method('setScopes')->with([]);
        $this->googleClientMock->method('setAuthConfig')->with(['secret' => 'qwerty']);
        $this->googleClientMock->method('createAuthUrl')->willReturn('https://google.com/auth');
        $this->googleClientMock->method('fetchAccessTokenWithAuthCode')->with('foo2')->willReturn(['token' => 'asdfWEew1']);
        $this->googleClientMock->method('getAccessToken')->willReturn(['token' => 'asdfWEew1']);
        $this->googleClientMock->method('isAccessTokenExpired')->willReturn(false);

        $this->loggerMock->method('info')->withConsecutive(
            ['Getting the Google API client secret from the `'.$secretPath.'` file'],
            ['Sending the authentication code to Google'],
            ['Saving the access token to the `'.$tokenPath.'` file'],
            ['So subsequent executions will not prompt for authorization'],
            ['The Google authentication is completed']
        );

        $client = new GoogleAPIClient($this->googleClientMock, $this->loggerMock);
        $client->authenticate($secretPath, $tokenPath, [], function () { return 'foo2'; }, true);
        $this->assertEquals(['token' => 'asdfWEew1'], json_decode(file_get_contents($tokenPath), true), 'Created JSON token file is incorrect');
    }

    public function testFailAuthentication()
    {
        $secretPath = static::TEMP_DIR.DIRECTORY_SEPARATOR.'secret.json';
        $tokenPath = static::TEMP_DIR.DIRECTORY_SEPARATOR.'token.json';
        file_put_contents($secretPath, '{"secret": "qwerty"}');

        $this->googleClientMock->method('setScopes')->with([]);
        $this->googleClientMock->method('setAuthConfig')->with(['secret' => 'qwerty']);
        $this->googleClientMock->method('createAuthUrl')->willReturn('https://google.com/auth');
        $this->googleClientMock->method('fetchAccessTokenWithAuthCode')->with('foo1')->willReturn(['error' => 'test', 'error_description' => 'This is a test']);

        $this->loggerMock->method('info')->withConsecutive(
            ['Getting the Google API client secret from the `'.$secretPath.'` file'],
            ['Sending the authentication code to Google']
        );

        $this->expectException('RuntimeException');
        $this->expectExceptionMessage('Google has declined the auth code: This is a test');
        $client = new GoogleAPIClient($this->googleClientMock, $this->loggerMock);
        $client->authenticate($secretPath, $tokenPath, [], function () { return 'foo1'; });
        $this->assertFileNotExists($tokenPath);
    }

    /**
     * @dataProvider getServiceProvider
     */
    public function testGetService($property, $shouldExist, $expectedClass = null)
    {
        $client = new GoogleAPIClient($this->googleClientMock);

        if ($shouldExist) {
            $this->assertInstanceOf($expectedClass, $client->$property);
        } else {
            $this->expectException('LogicException');
            $client->$property;
        }
    }

    public function getServiceProvider()
    {
        return [
            ['driveService', true, 'Google_Service_Drive'],
            ['sheetsService', true, 'Google_Service_Sheets'],
            ['slidesService', true, 'Google_Service_Slides'],
            ['fooService', false],
            ['bar', false]
        ];
    }

    public function testCommandAuthentication()
    {
        $this->googleClientMock->method('createAuthUrl')->willReturn('https://google.com/login');
        $this->googleClientMock->method('fetchAccessTokenWithAuthCode')->with('auth-code')->willReturn(['token' => '24DSFS']);
        $this->googleClientMock->method('getAccessToken')->willReturn(['token' => '24DSFS']);
        $this->googleClientMock->method('isAccessTokenExpired')->willReturn(false);

        $secretPath = static::TEMP_DIR.DIRECTORY_SEPARATOR.'secret.json';
        $tokenPath = static::TEMP_DIR.DIRECTORY_SEPARATOR.'token.json';
        file_put_contents($secretPath, '{"secret": "qwerty"}');

        $command = new class ($secretPath, $tokenPath, $this->googleClientMock) extends Command {
            public function __construct($secretPath, $tokenPath, $api) {
                parent::__construct('test');
                $this->secretPath = $secretPath;
                $this->tokenPath = $tokenPath;
                $this->api = $api;
            }
            protected function execute(InputInterface $input, OutputInterface $output) {
                $client = new GoogleAPIClient($this->api);
                $result = $client->authenticateFromCommand($input, $output, $this->secretPath, $this->tokenPath, []);
                return $result ? 0 : 1;
            }
        };

        $commandTester = new CommandTester($command);
        $commandTester->setInputs(['auth-code']);
        $commandTester->execute([]);
        $output = $commandTester->getDisplay();
        $this->assertContains('You need to authenticate to your Google account to proceed', $output);
        $this->assertContains('Open the following URL in a browser, get an auth code and paste it below', $output);
        $this->assertContains("\nhttps://google.com/login\n", $output);
        $this->assertEquals(0, $commandTester->getStatusCode());
        $this->assertFileExists($tokenPath);
        $this->assertEquals(['token' => '24DSFS'], json_decode(file_get_contents($tokenPath), true), 'Created JSON token file is incorrect');
    }

    public function testFailCommandAuthentication()
    {
        $secretPath = static::TEMP_DIR.DIRECTORY_SEPARATOR.'no-file.json';

        $command = new class ($secretPath) extends Command {
            public function __construct($secretPath) {
                parent::__construct('test');
                $this->secretPath = $secretPath;
            }
            protected function execute(InputInterface $input, OutputInterface $output) {
                $client = new GoogleAPIClient();
                $result = $client->authenticateFromCommand($input, $output, $this->secretPath, null, []);
                return $result ? 0 : 1;
            }
        };

        $commandTester = new CommandTester($command);
        $commandTester->execute([]);
        $output = $commandTester->getDisplay();
        $this->assertContains('Failed to authenticate to Google: Couldn\'t parse the client secret file: The `'.OutputFormatter::escape($secretPath).'` file doesn\'t exist', $output);
        $this->assertNotEquals(0, $commandTester->getStatusCode());
    }
}
