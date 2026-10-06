..  _known-problems:

==============
Known problems
==============

..  _known-problems-troubleshooting:

A task file is not imported
===========================

Check the TYPO3 log first, see :ref:`deployment-logging`.

The file is not valid YAML, or misses ``type`` or ``execution``
    The import stops until the file changes. Fix the file; the next request
    imports it.

The task type does not exist
    For example, the console command belongs to an uninstalled extension, or the
    command is not schedulable. Only this task is skipped; all others are
    imported.

The database schema is not up to date
    Run :bash:`vendor/bin/typo3 extension:setup`. The import is retried on every
    boot until it succeeds.

A site set's tasks are missing
    The set has to be used by a site, see :ref:`site-sets-activation`. After
    editing a site's :file:`config.yaml` by hand, flush the caches.

..  _known-problems-limitations:

Limitations
===========

*   Parameters of task classes differ between TYPO3 13 and 14, see
    :ref:`task-parameters-classes`. A file written for one version may need
    adjusting after an upgrade. Console command tasks are not affected.
*   The extension is in alpha state. The file format may still change.
