# Social Monitor Readme

## Installation

Clone the project using `git clone <path-to-git>`

Run `composer install` to install PHP libraries, including the Zend 1 framework (which no longer needs to be installed on your path)

Run `npm install` to install gulp dependencies, and compile css

Run `gulp` to set up CSS and JavaScript files

## Configuration

Configuration files are currently split between two locations. The Zend Framework configuration can be found in the `application/configs` folder. The Symfony Container configuration can be found in the base folder.

### Zend Framework configuration

To configure the project you will first need to copy the `application/configs/config.sample.yaml` to `application/configs/config.yaml`

For development purposes, update the `alpha` configuration (at the bottom) to use your own database connection parameters.

### Symfony container configuration

Currently there are also some parameters required in the `parameters.yml` that is used for the Symfony container.

To configure these, copy the `parameters.dist.yml` to `parameters.yml` and edit the database parameters to match your local settings. Some values should be copied from the dev/prod environment.

Symfony's services are defined in services.yml in the root directory of the project.