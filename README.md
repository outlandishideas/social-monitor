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

### Phinx Migrations

Added migrations via Phinx migration library.

To configure Phinx for your system, copy `phinx.dist.yml` to `phinx.yml`. Add your database details to the relevant environments and your ready to go.

To migrate Phinx, you will need to start from a clean database so make a backup of your current database (if you have one) and then drop all the tables in it to start from square one.

To migrate your database up to the latest version run `./vendor/bin/phinx migrate` from the base project folder. If this is your first migration Phinx will create a table in the database (called phinxlog by default) in which to store the status of the migrations. It will then run through all migrations that have not yet been run and apply the SQL to the database.

To create a new migration run `./vendor/bin/phinx create ANewMigration` from the base project folder. The name of the migration must be in Camel-case. This will create a new migration file in the `migrations` folder, which you can start working on.

For full documentation see the [Phinx Documentation](http://docs.phinx.org/en/latest/)

## Public folder

For security reasons, `index.php` has been moved to the `/public` folder so that configuration files in the base project folder are no longer publically accessible.

You will need to alter the configuration on your machine to make the `/public` folder the `DocumentRoot` for this project using either apache or nginx.

## Facebook API changes

As noted on the 01/06/2016 the Facebook API has a new limit on the number of posts it can return. It used to be 250 and it is now 100. We have changed this in the FeedFetcher class.

### Deployments

British Council Social Monitor is running on AWS. Deployment details can be found in [gitlab](https://gitlab.outlandish.com/social-monitor/social-monitor-infrastructure),
and require Ansible (preferably running on a linux virtual machine if using Windows)

The live site is at [socialmonitor.britishcouncil.net](https://socialmonitor.britishcouncil.net) (public IP: 54.72.25.164, internal IP: 10.0.0.131)

The staging site is at [staging.bc.out.re](https://staging.bc.out.re) (public IP: 52.51.21.217, internal IP: 10.0.4.28)

## Parameters

### Filtering index columns based on user level

As well as defining what columns should appear in each of the index page tables, you can now also define rules to stop some columns being viewed by some users. In the parameters.yml file add the following data:
```

table.rules:
  -
    user_level: 1
    columns:
      - @table.header.total_rank

```

The above configuration would hide the TotalRank Header from any index tables that include it for any user with a user_level of 1. Users with a different user_level would not be affected.