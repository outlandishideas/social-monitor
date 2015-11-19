# Social Monitor Readme

## Installation

Clone the project using `git clone <path-to-git>`

Run `composer install` to install PHP libraries, including the Zend 1 framework (which no longer needs to be installed on your path)

## Configuration

Configuration files are currently split between two locations. The Zend Framework configuration can be found in the `application/configs` folder. The Symfony Container configuration can be found in teh base folder.

### Zend Framework configuration

To configure the project you will first need to copy the `application/configs/config.sample.yaml` to `application/configs/config.yaml`

For development purposes, update the alpha configuration to use your database connection parameters.

### Symfony container configuration

Currently there are also some parameters required in the `parameters.yml` that is used for the Symfony container. To configure these, copy the `parameters.dist.yml` to `parameters.yml` and edit the database parameters to match your local settings.

