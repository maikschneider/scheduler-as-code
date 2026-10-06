<div align="center">

![Extension icon](Resources/Public/Icons/Extension.svg)

# TYPO3 extension `scheduler_as_code`

[![Latest version](https://typo3-badges.dev/badge/scheduler_as_code/version/shields.svg)](https://extensions.typo3.org/extension/scheduler_as_code)
[![Supported TYPO3 versions](https://typo3-badges.dev/badge/scheduler_as_code/typo3/shields.svg)](https://extensions.typo3.org/extension/scheduler_as_code)
[![Supported PHP versions](https://img.shields.io/packagist/dependency-v/maikschneider/scheduler-as-code/php?logo=php)](https://packagist.org/packages/maikschneider/scheduler-as-code)
[![Tests](https://img.shields.io/github/actions/workflow/status/maikschneider/scheduler-as-code/tests.yml?label=tests&logo=github)](https://github.com/maikschneider/scheduler-as-code/actions/workflows/tests.yml)
[![CGL](https://img.shields.io/github/actions/workflow/status/maikschneider/scheduler-as-code/sca.yml?label=cgl&logo=github)](https://github.com/maikschneider/scheduler-as-code/actions/workflows/sca.yml)
[![License](https://img.shields.io/badge/license-GPL--2.0--or--later-blue.svg)](LICENSE.md)

</div>

This TYPO3 extension keeps **scheduler tasks in YAML files** under version control. Scheduler tasks normally live
only in the database, so every environment drifts: a task created on staging never reaches production, and the
repository cannot tell which tasks a project is supposed to run. With this extension, the files are the source of
truth and the database follows them.

```yaml
# config/scheduler/cleanup-deleted.yaml

type: 'cleanup:deletedrecords'
description: 'Remove deleted records older than 30 days'
group: 'Maintenance'
execution:
  frequency: '0 3 * * *'
parameters:
  options:
    min-age: 30
```

Commit the file and deploy. The task appears in the Scheduler module on the next request, with no extra deployment
step.

## ✨ Features

**[Automatic import](https://docs.typo3.org/p/maikschneider/scheduler-as-code/main/en-us/Deployment/Index.html)**: Runs when TYPO3 boots after the task files changed or the caches were flushed
* New files create tasks, changed files update them, removed files disable them
* A task deleted in the backend comes back while its file exists
* Last execution, running executions and failures are never touched

**[Export](https://docs.typo3.org/p/maikschneider/scheduler-as-code/main/en-us/Usage/Index.html#usage-export)**: `scheduler:export` turns existing tasks into task files and links them

**[Task files](https://docs.typo3.org/p/maikschneider/scheduler-as-code/main/en-us/Configuration/TaskFiles.html)**: Console commands and task classes, cron expressions or intervals
* [Arguments and options](https://docs.typo3.org/p/maikschneider/scheduler-as-code/main/en-us/Configuration/Parameters.html) as plain maps
* Task groups by name, created on demand

**[Site sets](https://docs.typo3.org/p/maikschneider/scheduler-as-code/main/en-us/Configuration/SiteSets.html)**: Extensions ship tasks with a site set
* Imported while a site uses the set, disabled when none does
* A project file overrides the set's task

**[Badges in the Scheduler module](https://docs.typo3.org/p/maikschneider/scheduler-as-code/main/en-us/Usage/Index.html#usage-backend)**: See at a glance which tasks come from a file
* *Managed in file*, with the source file on hover
* *Out of sync* for tasks changed in the backend since their last import or export
* *File removed* for tasks whose file is gone

## 🔥 Installation

### Requirements

* TYPO3 13.4 LTS or 14.3 LTS
* PHP 8.2+

### Composer

[![Packagist](https://img.shields.io/packagist/v/maikschneider/scheduler-as-code?label=version&logo=packagist)](https://packagist.org/packages/maikschneider/scheduler-as-code)
[![Packagist Downloads](https://img.shields.io/packagist/dt/maikschneider/scheduler-as-code?color=brightgreen)](https://packagist.org/packages/maikschneider/scheduler-as-code)

```bash
composer require maikschneider/scheduler-as-code
```

### TER

[![TER version](https://typo3-badges.dev/badge/scheduler_as_code/version/shields.svg)](https://extensions.typo3.org/extension/scheduler_as_code)
[![TER downloads](https://typo3-badges.dev/badge/scheduler_as_code/downloads/shields.svg)](https://extensions.typo3.org/extension/scheduler_as_code)

Download the zip file from [TYPO3 extension repository (TER)](https://extensions.typo3.org/extension/scheduler_as_code).

## 📂 Setup

Create the extension's database columns:

```bash
vendor/bin/typo3 extension:setup
```

Then export the tasks you already have and commit them:

```bash
vendor/bin/typo3 scheduler:export
git add config/scheduler
```

From now on, change tasks in their files. See
[Usage](https://docs.typo3.org/p/maikschneider/scheduler-as-code/main/en-us/Usage/Index.html) for what each change
does to a task.

## 📙 Documentation

Please have a look at the
[official extension documentation](https://docs.typo3.org/p/maikschneider/scheduler-as-code/main/en-us/Index.html).

## 🧑‍💻 Contributing

Please have a look at [`CONTRIBUTING.md`](CONTRIBUTING.md).

## ⭐ License

This project is licensed under [GNU General Public License 2.0 (or later)](LICENSE.md).
