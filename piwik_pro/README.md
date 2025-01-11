# Piwik PRO

A simple module to add the Piwik PRO container (with tracking code) to your Drupal site,
making it easy to collect visitor data from any Drupal site.

[Piwik PRO](https://piwik.pro/) is a privacy-first platform that offers advanced analytics features while
allowing for full control of data. It provides flexible reports and data collection in
addition to consent management, tag management and a customer data platform.

For a full description of the module, visit the [project page](https://www.drupal.org/project/piwik_pro).

To submit bug reports and feature suggestions or to track changes, visit the [issue tracker](https://www.drupal.org/project/issues/piwik_pro).

Note that this is not for the original Piwik (or later Matomo).
For these products, please use the [Piwik module](https://www.drupal.org/project/piwik).

## Table of contents

 * [Requirements](#requirements)
 * [Installation](#installation)
 * [Configuration](#configuration)
   * [Page specific tracking](#page-specific-tracking)
   * [Role specific tracking](#role-specific-tracking)
   * [Content type specific tracking](#content-type-specific-tracking)
 * [Contributing](#contributing)


## Requirements

This module requires no modules outside of Drupal core.
You must register for a Piwik-Pro account.


## Installation

This module suite is installed like any other contributed module. For further
information, see [Installing Drupal Modules](https://drupal.org/docs/extending-drupal/installing-drupal-modules).

## Configuration

    1. Navigate to Administration > Extend and enable the module.
    2. Navigate to Administration > Configuration > Web services > Piwik PRO
       to configure the Piwik PRO account.

These defaults are changeable by the website administrator or any other
user with 'Administer Piwik PRO' permission.

### Page specific tracking

Allows the administrator to define the page paths where the Piwik PRO code is run.

The default is set to "Add to every page except the listed pages". By
default the following pages are listed for exclusion:

```
/admin
/admin/*
/batch
/node/add*
/node/*/*
/user/*/*
```

### Role specific tracking

Allows the administrator to limit the user roles for which the Piwik PRO scripts are loaded.

The default is set to "Every role except the selected roles". It is also possible to invert the setting to only selected roles.

### Content type specific tracking

Allows the administrator to limit the content types for which the Piwik PRO scripts are loaded.

The default is set to "Every content type except the selected content types". It is also possible to invert the setting to only selected content types.

## Contributing

DDEV configuration is available for local development. To get started, install [DDEV](https://ddev.readthedocs.io/en/stable/users/install/ddev-installation/)
and run `ddev start` in the project root. The DDEV add-on [ddev-drupal-contrib](https://github.com/ddev/ddev-drupal-contrib)
is also available.
