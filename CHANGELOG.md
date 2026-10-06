# Changelog

All notable changes to this project are documented in this file.
The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [0.1.0] - 2026-10-06

### Added

- *Out of sync* badge for file-managed tasks that were changed in the database since their
  last import or export.
- Site sets can ship tasks in `Configuration/Sets/<Set>/scheduler/`. They are imported while
  a site uses the set; project files in `config/scheduler/` override them.
- The Scheduler module badge shows the file a task comes from.
- Task definitions in `config/scheduler/*.yaml`, one task per file, identified by file name.
- Automatic import on boot whenever the task files changed or the caches were flushed:
  new files create tasks, changed files update them, removed files disable them.
- `scheduler:export` command to turn existing tasks into task files.
- *Managed in file* and *File removed* badges in the Scheduler module.
- Support for TYPO3 13.4 (serialized tasks) and 14.3 (TCA-based tasks).
