#!/bin/bash

# Ensure Xdebug is enabled for coverage
export XDEBUG_MODE=coverage

# Run PHPUnit with coverage options
php -dxdebug.mode=coverage vendor/bin/phpunit --coverage-clover=coverage.clover

# Check if coverage file was generated
if [ -f "coverage.clover" ]; then
    echo "Coverage file generated successfully"
    
    # If ocular is available, upload to Scrutinizer
    if [ -f "ocular.phar" ]; then
        php ocular.phar code-coverage:upload --format=php-clover coverage.clover
    else
        echo "Downloading ocular.phar..."
        wget https://scrutinizer-ci.com/ocular.phar
        php ocular.phar code-coverage:upload --format=php-clover coverage.clover
    fi
else
    echo "Failed to generate coverage file"
    exit 1
fi 