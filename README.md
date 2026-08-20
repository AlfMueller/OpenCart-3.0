

# OpenCart 3.0 community fork

[![PHP compatibility](https://github.com/AlfMueller/OpenCart-3.0/actions/workflows/php-compatibility.yml/badge.svg)](https://github.com/AlfMueller/OpenCart-3.0/actions/workflows/php-compatibility.yml)

This repository contains the OpenCart  wallee payment module that enables the shop to process payments with [wallee](https://www.wallee.com).

This is an unofficial, community-maintained fork. Its primary compatibility
target is OpenCart 3.0.3.8 running on PHP 8.4.

##### To use this extension, a [wallee](https://app-wallee.com/user/signup) account is required.

## Requirements

* [OpenCart](https://www.opencart.com/) 3.0.3.8
* PHP 8.2, 8.3, or 8.4

> **Note:**
> Support for OpenCart **3.0.5 and later versions** is not available in this module.  
Please contact **wallee** to discuss available options for newer OpenCart versions.

## Development

Run all local checks with Composer:

```bash
composer install
composer check
```

The checks lint every PHP file with all runtime warnings enabled and exercise
the webhook URL migration scenarios. GitHub Actions runs them on PHP 8.2, 8.3,
and 8.4.

## Documentation

* [English](https://plugin-documentation.wallee.com/wallee-payment/opencart-3.0/1.0.59/docs/en/documentation.html)

## Support

Support queries can be issued on the [wallee support site](https://app-wallee.com/space/select?target=/support).

## License

Please see the [license file](https://github.com/wallee-payment/opencart-3.0/blob/1.0.59/LICENSE) for more information.
