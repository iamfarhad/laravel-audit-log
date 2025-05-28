# Testing Laravel Audit Logger

This document provides instructions for testing the Laravel Audit Logger package.

## Running Tests in Docker Environment

If you're using Docker for development, you can use the following Makefile commands from the project root:

```bash
# Run PHPUnit tests with coverage
make audit-test

# Run Laravel Pint code style check
make audit-pint

# Fix code style issues with Laravel Pint
make audit-pint-fix

# Run PHPStan static analysis
make audit-analyse

# Run both Pint and PHPStan
make audit-cs

# Run all tests (PHPUnit, Pint, and PHPStan)
make audit-all
```

## Running Tests Manually

If you prefer to run tests manually within the package directory:

```bash
# Run PHPUnit tests
vendor/bin/phpunit

# Run Laravel Pint code style check
vendor/bin/pint --test

# Fix code style issues
vendor/bin/pint

# Run PHPStan static analysis (with increased memory limit)
php -d memory_limit=256M vendor/bin/phpstan analyse

# Run both Pint and PHPStan
composer cs
```

## Using the Testing Script

For an interactive testing experience, you can use the provided script:

```bash
# From the project root
docker compose exec app bash packages/audit-logger/docker-test.sh

# Or from the package directory
./docker-test.sh
```

This will provide an interactive menu with options to run different test suites.

## GitHub Actions Workflows

The package includes several GitHub Actions workflows for continuous integration:

1. **Tests**: Runs PHPUnit tests across different PHP and Laravel versions.
2. **Coding Standards**: Checks code style using Laravel Pint.
3. **PHPStan Static Analysis**: Performs static analysis using PHPStan.
4. **Laravel Integration Test**: Tests the package in a real Laravel application.

These workflows run automatically on push to main/master branches, on pull requests, and on scheduled intervals.

## Notes

- When running PHPStan, you may need to increase the PHP memory limit if you encounter memory issues.
- For test coverage reports, ensure Xdebug is installed and enabled with coverage mode.
- Tests are run with `XDEBUG_MODE=coverage` to enable coverage reports. 