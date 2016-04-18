# Quicksilver CLI

An experimental Commandline tool for managing Quicksilver operations on
Pantheon.

Preliminary work -- functionality may be renamed, removed, or moved to another
component.

## Installation

```
composer global require pantheon-systems/quicksilver-cli
```

Ensure that ~/.composer/vendor/bin is in your global `$PATH`.

### Configuration
```
mkdir ~/.quicksilver
cp example-user-config.yml ~/.quicksilver/quicksilver.yml
```
See contents of file for customization instructions.

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
Installs one of the example projects from quicksilver-examples or the quicksilver script repository identified in `quicksilver.yml`, and updates the site's `pantheon.yml` file with the code snippet included in that directory's `readme.md`.

Edit the pantheon.yml file to suit.

