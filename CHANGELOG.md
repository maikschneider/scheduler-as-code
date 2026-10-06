# Changelog

All notable changes to this project are documented in this file.
The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added

- Task definitions in `config/scheduler/*.yaml`, one task per file, identified by file name.
- Automatic import on boot whenever the task files changed or the caches were flushed:
  new files create tasks, changed files update them, removed files disable them.
- `scheduler:export` command to turn existing tasks into task files.
- *Managed in file* and *File removed* badges in the Scheduler module.
- Support for TYPO3 13.4 (serialized tasks) and 14.3 (TCA-based tasks).
