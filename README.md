# TYPO3 Extension core_upgrader2

[![Latest Stable Version](https://img.shields.io/packagist/v/wapplersystems/core-upgrader.svg)](https://packagist.org/packages/ichhabrecht/core-upgrader)

Run upgrade wizards for multiple TYPO3 versions (to 14.x) at once.

## Features

This extension allows to upgrade the TYPO3 core from v7.6 to v12.4 with this extension and the rest by the v14 core in one step.

Version 14.x of this extension requires TYPO3 v14. For an upgrade to TYPO3 v13 use version 13.x.

Differences from the original Core Upgrade Wizards:

* The Text/Textpic/Image to Textmedia Wizard has been split into optional wizards
* Some obsolete wizards were removed, because their result cannot be used in version 14 already.

## Installation

Simply install the extension with Composer or download from [TER](https://extensions.typo3.org/extension/core_upgrader2/).

`composer require wapplersystems/core-upgrader`

## Usage

1. Now you can list all update wizards:

   `typo3 upgrade:list`

1. Now you can run all update wizards:

   `typo3 upgrade:run`

