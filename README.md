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
use Symfony\Component\Console\Output\OutputInterface;

$googleClient = new GoogleAPIClient();

$googleClient->authenticate(
    'google-client-secret.json',
    'google-access-token.json',
    [\Google_Service_Drive::DRIVE_READONLY, \Google_Service_Sheets::SPREADSHEETS_READONLY],
    function ($authURL) use ($input, $output) {
        $output->writeln('Open the following URL in a browser, get an auth code and paste it below:');
        $output->writeln($authURL, OutputInterface::OUTPUT_PLAIN);

        $helper = $this->getHelper('question');
        $question = new Question('Auth code: ');
        $question->setNormalizer('trim');
        return $helper->ask($input, $output, $question);
    }
);

$file = $googleAPIClient->driveService->files->get('87ad6fg90gr0m91c84');
```

* `'google-client-secret.json'` is a path to a Google API client secret file. You can learn how to get it from [this instruction](HowTo-GoogleAPISetup).
* `'google-access-token.json'` is a path to a file where to store a Google API access token so the helper won't prompt to authenticate next time. This file is optional and the value can be `null`.
* The third argument is a list of required permissions.

You can find more information in the source code. 
