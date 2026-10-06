..  _introduction:

============
Introduction
============

..  _what-it-does:

What does it do?
================

TYPO3 stores scheduler tasks only in the database. Each environment therefore keeps
its own set of tasks: a task created on staging never reaches production, and the
repository cannot tell which tasks a project is supposed to run.

Scheduler as Code moves task definitions into YAML files, one task per file, in
:file:`config/scheduler/`. The files are committed with the project and imported
into :sql:`tx_scheduler_task` whenever they change. The Scheduler module keeps
working as before: tasks run, report and fail exactly like tasks created in the
backend.

..  _features:

Features
========

*   **Automatic import.** New files create tasks, changed files update them, and
    removed files disable their tasks. The import runs when TYPO3 boots after the
    files changed or the caches were flushed, so a deployment needs no extra step.
*   **Export of existing tasks.** :bash:`scheduler:export` turns tasks created in
    the backend into task files and links them.
*   **Files win.** A file-managed task deleted in the backend comes back on the
    next import.
*   **Site sets.** Extensions can ship tasks with a site set. They are imported
    while a site uses the set, and a project file can override them.
*   **Badges in the Scheduler module** show which tasks are managed in a file and
    which ones lost their file.
*   **Runtime state stays untouched.** Last execution, running executions and
    failures are never overwritten by an import.
*   Supports TYPO3 13.4 LTS, where tasks are serialized objects, and TYPO3 14.3
    LTS, where tasks are TCA records.
