..  _deployment:

==========
Deployment
==========

There is no import command. The import runs while TYPO3 boots, in the first web
request or CLI command after one of these events:

*   a task file was added, changed or removed,
*   the set of task files changed because a site started or stopped using a site
    set,
*   the caches were flushed, for example with :bash:`vendor/bin/typo3 cache:flush`.

Each boot only checks the modification times of the task files and compares them
with a cached fingerprint, which takes a fraction of a millisecond. The task files
are read only when the fingerprint changed. A lock makes sure that concurrent
requests do not import twice.

..  _deployment-steps:

What a deployment needs
=======================

Most deployments already do everything that is needed:

#.  Ship the task files with the code.
#.  Update the database schema, for example with
    :bash:`vendor/bin/typo3 extension:setup`. Until the columns of this
    extension exist, the import is postponed to the next boot.
#.  Flush the caches.

Deployer, Surf or plain shell scripts need no extra task. Do not run
:bash:`scheduler:export` on a server: task files belong in version control, and
an export there would only change the release directory.

..  _deployment-logging:

Logging
=======

The import never shows errors to visitors. It writes to the TYPO3 log, component
``MaikSchneider.SchedulerAsCode.EventListener.SynchronizeTasksOnBoot``:

*   errors for task files that cannot be imported,
*   warnings when the import has to be retried, typically because the database
    schema is not up to date yet,
*   information about created, updated and disabled tasks. TYPO3 does not write
    information messages to the log by default; lower the log level to see them.

See :ref:`t3coreapi:logging` for how to configure log writers.
