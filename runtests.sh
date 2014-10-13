#!/bin/bash

config="$1"
runoptional="$2"

if [[ ! $# == 2 ]]; then
	printf "Usage: runtests [path to configfile] [y/n for running selenium tests]\n"
fi

vendor/bin/phpunit --configuration $1 tests/
exitcode="$?"

if [ $exitcode -eq 0 ]; then
	if [ "$runoptional" == "y" ]; then
		printf "Start running Selenium tests.\n"
		vendor/bin/phpunit --colors --configuration $1 tests/SeleniumTest.php.optional
		exitcode="$?"
	fi
fi

exit $exitcode