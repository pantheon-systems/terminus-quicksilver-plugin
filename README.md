# Quicksilver CLI

An experimental Commandline tool for managing Quicksilver operations on
Pantheon.

## Installation

```
composer global install pantheon-systems/quicksilver-cli
```

Ensure that ~/.composer/vendor/bin is in your global `$PATH`.

## Commands

The following commands are supported:

### About
```
quicksilver-cli about
```
Prints version and other information about the quicksilver-cli tool.

### Install
```
cd /path/to/local/pantheon/site
quicksilver-cli install debug
```
Installs one of the example projects from quicksilver-examples, and updates
the site's pantheon.yml file.

Edit the pantheon.yml file to suit.
