# A flexible media library for Laravel

[![Latest Version on Packagist](https://img.shields.io/packagist/v/finller/laravel-media.svg?style=flat-square)](https://packagist.org/packages/finller/laravel-media)
[![GitHub Tests Action Status](https://img.shields.io/github/actions/workflow/status/finller/laravel-media/run-tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/finller/laravel-media/actions?query=workflow%3Arun-tests+branch%3Amain)
[![GitHub Code Style Action Status](https://img.shields.io/github/actions/workflow/status/finller/laravel-media/fix-php-code-style-issues.yml?branch=main&label=code%20style&style=flat-square)](https://github.com/finller/laravel-media/actions?query=workflow%3A"Fix+PHP+code+style+issues"+branch%3Amain)
[![Total Downloads](https://img.shields.io/packagist/dt/finller/laravel-media.svg?style=flat-square)](https://packagist.org/packages/finller/laravel-media)

This package provide an extermly flexible media library, allowing you to store any files with their conversions (nested conversions are supported).
It is designed to be usable with local upload/conversions and with cloud upload/conversions solutions like Bunny.net Stream, AWS MediaConvert, Transloadit, ...

It takes its inspiration from the wonderful spatie/laravel-media-library package (check spatie packages, they are really great),but it's not a fork. The migration from `spatie/laravel-media-library` is possible but not that easy if you want to keep your conversions files.

## Installation

You can install the package via composer:

```bash
composer require finller/laravel-media
```

You can publish and run the migrations with:

```bash
php artisan vendor:publish --tag="laravel-media-migrations"
php artisan migrate
```

You can publish the config file with:

```bash
php artisan vendor:publish --tag="laravel-media-config"
```

This is the contents of the published config file:

```php
return [
];
```

Optionally, you can publish the views using

```bash
php artisan vendor:publish --tag="laravel-media-views"
```

## Usage

```php
$Media = new Finller\Media();
echo $Media->echoPhrase('Hello, Finller!');
```

## Testing

```bash
composer test
```

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Contributing

Please see [CONTRIBUTING](CONTRIBUTING.md) for details.

## Security Vulnerabilities

Please review [our security policy](../../security/policy) on how to report security vulnerabilities.

## Credits

-   [Quentin Gabriele](https://github.com/finller)
-   [All Contributors](../../contributors)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
