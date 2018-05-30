# xml-authoring-library
A place to put code that is reused by many commands which are part of the [xml-authoring-tools suite](https://github.com/forikal-uk/xml-authoring-tools).

Created as a place to put code which is re-used by two or more projects in the forikal xml-authoring suite.

See the related [Issue that triggered the creation of this project](https://github.com/forikal-uk/xml-authoring-tools/issues/3).


# This project can be added to your project via composer

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
