..  _usage:

=====
Usage
=====

..  _usage-export:

Exporting existing tasks
========================

Start by exporting the tasks you already have. Every task that is not linked to a
file yet is written to :file:`config/scheduler/`:

..  code-block:: bash

    vendor/bin/typo3 scheduler:export
    git add config/scheduler

Exported tasks are linked to their file right away, so the next import finds them
unchanged.

..  list-table::
    :header-rows: 1
    :widths: 45 55

    *   - Command
        - Effect
    *   - :bash:`scheduler:export`
        - Export all tasks that are not linked to a file yet.
    *   - :bash:`scheduler:export 3 7`
        - Export the tasks with uid 3 and 7.
    *   - :bash:`scheduler:export 3 --identifier=nightly-cleanup`
        - Export task 3 to :file:`config/scheduler/nightly-cleanup.yaml`.
    *   - :bash:`scheduler:export --force`
        - Also re-export tasks that are already linked, overwriting their files.

Without :bash:`--identifier`, the file name is derived from the task type, for
example :file:`cleanup-deletedrecords.yaml` for the console command
``cleanup:deletedrecords``.

..  _usage-lifecycle:

Working with task files
=======================

From now on, change tasks in their files, not in the backend. The import picks up
every change, see :ref:`deployment` for when exactly it runs.

..  list-table::
    :header-rows: 1
    :widths: 35 65

    *   - Change
        - Effect on the task
    *   - New file
        - A task is created.
    *   - Changed file
        - The task is updated. Its run history is kept.
    *   - Removed file
        - The task is disabled and marked :guilabel:`File removed`. It is not
          deleted, so its history stays available.
    *   - Restored file
        - The task is enabled and updated again.
    *   - Renamed file
        - The file name is the task's identifier. A renamed file creates a new
          task and disables the old one.
    *   - Task deleted in the backend
        - The task comes back on the next import while its file exists.

The file format is described in :ref:`task-files`.

..  _usage-backend:

Badges in the Scheduler module
==============================

The task list in :guilabel:`System > Scheduler` marks tasks that are linked to a
file. Hover a badge to see the file.

:guilabel:`Managed in file`
    The task is imported from a task file.

:guilabel:`Out of sync`
    The task was changed in the database since its last import or export, for
    example in the backend. The change is kept until the task file changes; the
    next import then overwrites it. To keep the change, write it to the file:

    ..  code-block:: bash

        vendor/bin/typo3 scheduler:export <uid> --force

    Running a task does not count as a change, and neither does the scheduler
    disabling a single-run task after its run.

:guilabel:`File removed`
    The task file no longer exists, so the task was disabled. Delete the task, or
    restore the file to import it again.
