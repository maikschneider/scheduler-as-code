..  _start:

=================
Scheduler as Code
=================

:Extension key:
    scheduler_as_code

:Package name:
    maikschneider/scheduler-as-code

:Version:
    |release|

:Language:
    en

:Author:
    Maik Schneider & Contributors

:License:
    This document is published under the
    `Open Publication License <https://www.opencontent.org/openpub/>`__.

:Rendered:
    |today|

----

Scheduler as Code keeps TYPO3 scheduler tasks in YAML files under version control.
Tasks are imported into the database automatically, so every environment runs the
tasks the repository describes.

----

..  card-grid::
    :columns: 1
    :columns-md: 2
    :gap: 4
    :class: pb-4
    :card-height: 100

    ..  card:: :ref:`Introduction <introduction>`

        What the extension does, and how it differs from the scheduler on its own.

    ..  card:: :ref:`Installation <installation>`

        Install the extension with Composer and set up the database.

    ..  card:: :ref:`Usage <usage>`

        Export existing tasks, edit task files, and read the badges in the
        Scheduler module.

    ..  card:: :ref:`Task files <task-files>`

        Reference of every key in a task file, including task parameters.

    ..  card:: :ref:`Site sets <site-sets>`

        Ship tasks with an extension and activate them per site.

    ..  card:: :ref:`Deployment <deployment>`

        When the import runs, and what a deployment has to do.

    ..  card:: :ref:`Known problems <known-problems>`

        Troubleshooting failed imports and current limitations.

..  toctree::
    :maxdepth: 2
    :titlesonly:
    :hidden:

    Introduction/Index
    Installation/Index
    Usage/Index
    Configuration/Index
    Deployment/Index
    KnownProblems/Index
