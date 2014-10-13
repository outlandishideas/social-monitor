#!/bin/bash

config="$1"
runoptional="$2"

if [[ ! $# == 2 ]]; then
	echo "Usage: runtests [path to configfile] [y/n for running selenium tests]"
fi

vendor/bin/phpunit --colors --configuration $1 tests/

if [ $? -eq 0 ] && [ "$runoptional" == "y" ]; then
	vendor/bin/phpunit --colors --configuration $1 tests/SeleniumTest.php.optional
fi

exit $?