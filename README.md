# TYPO3 Extension core_upgrader2

[![Latest Stable Version](https://img.shields.io/packagist/v/wapplersystems/core-upgrader.svg)](https://packagist.org/packages/ichhabrecht/core-upgrader)

Run upgrade wizards for multiple TYPO3 versions in one migration flow.

## Features

This extension allows to execute legacy/core migration steps in one flow.

### v14 status in this project

This repository variant is prepared for **TYPO3 v14** and contains additional adjustments for running the imported v13 wizard classes in a v14 installation.

Differences from the original Core Upgrade Wizards:

* The Text/Textpic/Image to Textmedia Wizard has been split into optional wizards
* Some obsolete wizards were removed, because their result cannot be used in newer TYPO3 versions.

## Installation

Simply install the extension with Composer or download from [TER](https://extensions.typo3.org/extension/core_upgrader2/).

`composer require wapplersystems/core-upgrader`

## Usage

1. Now you can list all update wizards:

   `typo3 upgrade:list`

1. Now you can run all update wizards:

   `typo3 upgrade:run`

## Run single migrations

Run one specific wizard by identifier:

```bash
typo3 upgrade:run <wizardIdentifier>
```

Examples:

```bash
typo3 upgrade:run sysLogSerialization
```
