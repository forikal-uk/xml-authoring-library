# xml-authoring-library

This is a place to put code that is reused by many commands which are part of the [xml-authoring-tools suite](https://github.com/forikal-uk/xml-authoring-tools).

Created as a place to put code which is re-used by two or more projects in the forikal xml-authoring suite.

See the related [Issue that triggered the creation of this project](https://github.com/forikal-uk/xml-authoring-tools/issues/3).

## Common Documentation

A library of help pages. I.e Information that is relevant to more than one project in this suite of projects.

- [How To: Google API Setup](https://github.com/forikal-uk/xml-authoring-library/blob/master/HowTo-GoogleAPISetup.md)

## This project can be added to your project via composer

```
$ composer require forikal-uk/xml-authoring-library --prefer-source
```

If you would like to develop both the library and, say a command that uses it in tandem. I beleive that composer allows you to grab the library as *source*

See : [composer require](https://getcomposer.org/doc/03-cli.md#require)

Quote:
> The option:
> --prefer-source: Install packages from source when available.

and see [Composer Repositories > Packages](https://getcomposer.org/doc/05-repositories.md#package)

Quote:
> *Source*: The source is used for development. This will usually originate from a source code repository, such as git. You can fetch this when you want to modify the downloaded package.

## Code Reference

### Google API helper

An abstraction over [Google API SDK](https://github.com/google/google-api-php-client).
It helps to mock Google API in tests and authenticate users in console commands.

You need to install Google SDK to your project to use this helper:

```bash
composer require google/apiclient:^2.0
```

Usage example:

```php
use Forikal\Library\GoogleAPI\GoogleAPIClient;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class MyCommand extends Command
{
    execute(InputInterface $input, OutputInterface $output)
    {
        $googleClient = new GoogleAPIClient(); // You can pass a \Google_Client mock to the constructor. It will be use in the services.
        
        if (!$googleClient->authenticateFromCommand(
            $input,
            $output
            'google-client-secret.json', // A path to a Google API client secret file
            'google-access-token.json', // A path to a file where to store a Google API access token so the helper won't prompt to authenticate next time
            [\Google_Service_Drive::DRIVE_READONLY, \Google_Service_Sheets::SPREADSHEETS_READONLY] // A list of required permissions
        )) {
            return 1;
        }
        
        // $googleAPIClient->driveService is a \Google_Service_Drive instance. All the other services are available.
        $file = $googleAPIClient->driveService->files->get('87ad6fg90gr0m91c84');
        
        return 0;
    }
}
```

You can learn how to get it from [this instruction](HowTo-GoogleAPISetup.md).
If you need to customize the console messages and behaviour, use the `authenticate` method.
You can find more information in the source code. 

### Console logger

A [PSR-3](https://github.com/php-fig/log) compatible logger which writes messages to a Symfony console output.
In contrast to the [built-in Synfony Console logger](https://symfony.com/doc/3.4/components/console/logger.html), this doesn't print log level labels and is customizable (we can edit the source code).

Usage example:

```php
use Forikal\Library\Console\ConsoleLogger;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\ConsoleOutputInterface;
use Psr\Log\LogLevel;

// ...

protected function execute(InputInterface $input, OutputInterface $output)
{
    $consoleLogger = new ConsoleLogger($output, [
        LogLevel::DEBUG  => OutputInterface::VERBOSITY_VERY_VERBOSE,
        LogLevel::INFO   => OutputInterface::VERBOSITY_VERBOSE,
        LogLevel::NOTICE => OutputInterface::VERBOSITY_NORMAL
    ], [
        LogLevel::DEBUG  => '',
        LogLevel::INFO   => '',
        LogLevel::NOTICE => 'info'
    ]);
    
    $service = new MyService($consoleLogger);
}
```

The constructor arguments are the same as in the [Synfony Console logger](https://symfony.com/doc/3.4/components/console/logger.html).

## Contribution

1. Clone the repository.
2. Install the dependencies by running `composer install` in a console.
3. Make a change. Make sure the code follows the [PSR-2 standard](https://github.com/php-fig/fig-standards/blob/master/accepted/PSR-2-coding-style-guide.md).
4. Test the code by running `composer test`. If the test fails, fix the code.
5. Commit and push the changes.
