..  _task-parameters:

===============
Task parameters
===============

:confval:`task-file-parameters` holds the settings of the task type. Their form
depends on whether the task is a console command or a task class.

..  _task-parameters-commands:

Console commands
================

Arguments and options are plain maps, keyed by their names as the command defines
them. An option without a value, a flag, is ``true``:

..  code-block:: yaml

    type: 'cleanup:deletedrecords'
    execution:
      frequency: '0 3 * * *'
    parameters:
      arguments:
        table: sys_log
      options:
        min-age: 30
        dry-run: true

Options that are not listed, or set to ``false``, are not passed to the command.

..  _task-parameters-classes:

Task classes
============

Task classes keep their settings differently in each TYPO3 version, so their
parameters are version specific:

TYPO3 14
    The names of the task's TCA fields, as in the task form of the Scheduler
    module.

TYPO3 13
    The names of the properties of the task class.

..  code-block:: yaml

    type: 'TYPO3\CMS\Scheduler\Task\OptimizeDatabaseTableTask'
    execution:
      frequency: 86400
    parameters:
      # TYPO3 13: selectedTables
      selected_tables:
        - sys_log
        - sys_history

..  tip::

    Create the task once in the Scheduler module and run
    :bash:`scheduler:export`. The exported file uses the right names for the
    TYPO3 version you run.
