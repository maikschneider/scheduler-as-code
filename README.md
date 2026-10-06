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

> **Status:** alpha. The file format may still change.

## How it works

- **Task files** live in `config/scheduler/`, one task per file.
- **Import is automatic.** When TYPO3 boots and the task files changed, or the caches were
  flushed, new files create tasks and changed files update them. A regular deployment that
  flushes caches needs no extra step.
- **Removing a file disables its task.** The task stays in the database, marked
  *File removed* in the Scheduler module. Restoring the file enables it again.
- **Files win.** A file-managed task that is deleted in the backend comes back on the next
  import.
- **The Scheduler module marks file-managed tasks** with a *Managed in file* badge.
- **Edits in the backend are flagged.** A file-managed task changed in the database since
  its last import or export shows *Out of sync*. The edit is kept until the file changes,
  which overwrites it. To keep it, write it to the file with `scheduler:export --force`.

Runtime state (last execution, running executions, failures) is never touched by an import,
and running a task does not count as an edit.

## Getting started

Export the tasks you already have, then commit the files:

```bash
vendor/bin/typo3 scheduler:export
git add config/scheduler
```

| Command | |
|---|---|
| `scheduler:export` | Export all tasks that are not linked to a file yet |
| `scheduler:export 3 7` | Export the tasks with uid 3 and 7 |
| `scheduler:export 3 --identifier=nightly-cleanup` | Choose the file name |
| `scheduler:export --force` | Also re-export linked tasks, overwriting their files |

Exported tasks are linked to their file at once, so the next import finds them unchanged.

## Task files

The file name, without extension, is the task's identifier and links the file to its database
record. Renaming a file therefore creates a new task and disables the old one.

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

| Key | | |
|---|---|---|
| `type` | required | A schedulable console command, or a task class |
| `description` | | Shown in the Scheduler module |
| `group` | | Task group by name; created if it does not exist |
| `disabled` | | `true` to import the task disabled |
| `priority` | | `50`, `100` (default) or `150`; TYPO3 14 only |
| `execution.frequency` | | Cron expression, or an interval in seconds |
| `execution.start` | | First run; required for a task without frequency, which runs once |
| `execution.end` | | Last run |
| `execution.multiple` | | `true` to allow parallel executions |
| `parameters` | | Task settings, see below |

Dates accept anything PHP's `DateTimeImmutable` understands, e.g. `'2026-01-01 04:00'`.

### Parameters

For **console commands**, arguments and options are plain maps. A flag is `true`:

```yaml
parameters:
  arguments:
    table: sys_log
  options:
    min-age: 30
    dry-run: true
```

For **task classes**, parameters are the task's own settings and differ between TYPO3
versions: TCA field names on TYPO3 14, class properties on TYPO3 13.

```yaml
type: 'TYPO3\CMS\Scheduler\Task\OptimizeDatabaseTableTask'
execution:
  frequency: 86400
parameters:
  selected_tables:          # TYPO3 13: selectedTables
    - sys_log
    - sys_history
```

`scheduler:export` writes the right names for the running version.

## Site sets

Extensions can ship tasks with a [site set](https://docs.typo3.org/permalink/t3coreapi:site-sets):
put task files into `Configuration/Sets/<Set>/scheduler/`.

```
EXT:my_sitepackage/Configuration/Sets/Maintenance/
├── config.yaml
└── scheduler/
    └── nightly-cleanup.yaml
```

- A set's tasks are imported while at least one site uses the set, directly or as a
  dependency of another set. Once no site uses it any more, its tasks are disabled.
- A file in `config/scheduler/` with the same name overrides the set's task, so a project
  can adjust, say, the frequency of a task a set ships. Delete the project file to go back
  to the set's version.
- Two sets shipping the same identifier stop the import, unless a file of that name in
  `config/scheduler/` replaces both. Otherwise rename one of them.

The badge in the Scheduler module shows which file a task comes from.

TYPO3 caches site configuration, so after editing a site's `config.yaml` by hand, flush the
caches for a changed set list to take effect. Changes saved in the Sites module do this
automatically.

## Troubleshooting

Import problems are logged, never shown to visitors:

- A file that is not valid YAML, or misses `type` or `execution`, stops the import until the
  file changes.
- A task whose type does not exist (e.g. an uninstalled extension) is skipped; the others
  are imported.
- If the database schema is not up to date yet during a deployment, the import is retried
  on the next boot.

## Installation

```bash
composer require maikschneider/scheduler-as-code
vendor/bin/typo3 extension:setup
```

## Development

See [CONTRIBUTING.md](CONTRIBUTING.md).
