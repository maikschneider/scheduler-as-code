# Scheduler as Code

Manage TYPO3 scheduler tasks as YAML files in version control.

Scheduler tasks normally live only in the database, so every environment drifts: a task
created on staging never reaches production, and nobody can tell from the repository which
tasks a project is supposed to run. This extension makes the repository the source of truth.

| | |
|---|---|
| **TYPO3** | 13.4 LTS, 14.3 LTS |
| **PHP** | 8.2, 8.3, 8.4 |
| **Extension key** | `scheduler_as_code` |
| **License** | GPL-2.0-or-later |

> **Status:** alpha, under development. The file format below may still change.

## Task files

Each task is one YAML file in `config/scheduler/`. The file name, without extension, is the
task's identifier and links the file to its database record, so renaming a file creates a new
task.

```yaml
# config/scheduler/cleanup-deleted.yaml
description: 'Remove deleted records older than 30 days'
command: 'cleanup:deletedrecords'
options:
  min-age: 30
frequency: '0 3 * * *'
group: 'Maintenance'
```

```yaml
# config/scheduler/optimize_tables.yaml
type: 'TYPO3\CMS\Scheduler\Task\OptimizeDatabaseTableTask'
frequency: 86400
parameters:
  selectedTables:
    - sys_log
    - sys_history
```

A file needs either `command` (a schedulable console command) or `type` (a task class).
Identifiers use lowercase letters, digits, `-` and `_`.

## Installation

```bash
composer require maikschneider/scheduler-as-code
```

## Development

See [CONTRIBUTING.md](CONTRIBUTING.md).
