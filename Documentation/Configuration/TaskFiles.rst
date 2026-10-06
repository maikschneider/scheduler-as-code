..  _task-files:

==========
Task files
==========

Each task is one YAML file in :file:`config/scheduler/`. The file name, without
the :file:`.yaml` or :file:`.yml` extension, is the task's **identifier**. It
links the file to its database record and may only contain lowercase letters,
digits, ``-`` and ``_``.

..  code-block:: yaml
    :caption: config/scheduler/cleanup-deleted.yaml

    type: 'cleanup:deletedrecords'
    description: 'Remove deleted records older than 30 days'
    group: 'Maintenance'
    execution:
      frequency: '0 3 * * *'
    parameters:
      options:
        min-age: 30

..  _task-files-reference:

Reference
=========

..  confval-menu::
    :name: task-file
    :display: table
    :type:
    :required:

    ..  confval:: type
        :name: task-file-type
        :type: string
        :required: true

        A schedulable console command, such as ``cleanup:deletedrecords``, or a
        scheduler task class, such as
        ``TYPO3\CMS\Scheduler\Task\OptimizeDatabaseTableTask``.

    ..  confval:: description
        :name: task-file-description
        :type: string

        Shown in the Scheduler module.

    ..  confval:: group
        :name: task-file-group
        :type: string

        Name of the task group. A group that does not exist yet is created.

    ..  confval:: disabled
        :name: task-file-disabled
        :type: bool
        :default: false

        Set to ``true`` to import the task disabled.

    ..  confval:: priority
        :name: task-file-priority
        :type: int
        :default: 100

        ``50`` (low), ``100`` (regular) or ``150`` (high). Only TYPO3 14 knows
        task priorities; TYPO3 13 ignores the key.

    ..  confval:: execution
        :name: task-file-execution
        :type: array
        :required: true

        When the task runs. Needs :confval:`task-file-execution-frequency`, or
        :confval:`task-file-execution-start` for a task that runs once.

    ..  confval:: execution.frequency
        :name: task-file-execution-frequency
        :type: string or int

        A cron expression such as ``'0 3 * * *'``, or an interval in seconds
        such as ``3600``. Without a frequency, the task runs once at
        :confval:`task-file-execution-start`.

    ..  confval:: execution.start
        :name: task-file-execution-start
        :type: string or int

        The first run, as a date or a Unix timestamp. Optional for recurring
        tasks: they then start counting from the import. Required for tasks
        without frequency.

    ..  confval:: execution.end
        :name: task-file-execution-end
        :type: string or int

        The last run. A task past its end is imported disabled.

    ..  confval:: execution.multiple
        :name: task-file-execution-multiple
        :type: bool
        :default: false

        Set to ``true`` to allow several executions of the task at the same time.

    ..  confval:: parameters
        :name: task-file-parameters
        :type: array

        The task's own settings, see :ref:`task-parameters`.

Dates accept every format PHP's :php:`DateTimeImmutable` understands, for example
``'2026-01-01 04:00'``. They are read in the server's time zone.

..  _task-files-examples:

Examples
========

..  code-block:: yaml
    :caption: An interval task that may run in parallel

    type: 'language:update'
    description: 'Update language packs hourly'
    priority: 150
    execution:
      frequency: 3600
      multiple: true
    parameters:
      options:
        fail-on-warnings: true

..  code-block:: yaml
    :caption: A task that runs once

    type: 'cleanup:orphanrecords'
    execution:
      start: '2026-12-24 22:00'
