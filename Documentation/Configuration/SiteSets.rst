..  _site-sets:

=========
Site sets
=========

Extensions can ship tasks with a :ref:`site set <t3coreapi:site-sets>`. Put the
task files into a :file:`scheduler/` folder next to the set's :file:`config.yaml`:

..  directory-tree::

    *   :path:`EXT:my_sitepackage/Configuration/Sets/Maintenance/`

        *   :file:`config.yaml`
        *   :path:`scheduler/`

            *   :file:`nightly-cleanup.yaml`

The task files use the same :ref:`format <task-files>` as project files.

..  _site-sets-activation:

Which tasks are imported
========================

*   A set's tasks are imported while at least one site uses the set, directly or
    as a dependency of another set it uses.
*   Once no site uses the set any more, its tasks are disabled, like tasks whose
    file was removed.
*   Sets with an invalid :file:`config.yaml` are skipped. TYPO3 reports them in
    the Sites module.

..  _site-sets-override:

Overriding a set's task
=======================

A file with the same name in :file:`config/scheduler/` overrides the set's task.
A project can use this to adjust, say, the frequency of a task a set ships:

..  code-block:: yaml
    :caption: config/scheduler/nightly-cleanup.yaml

    type: 'cleanup:deletedrecords'
    execution:
      frequency: '0 4 * * *'

Delete the project file to go back to the set's version.

Two sets that ship the same identifier stop the import, unless a project file of
that name replaces both. Otherwise, rename one of the files.

..  _site-sets-cache:

Changing a site's sets
======================

TYPO3 caches site configuration. Changes saved in the Sites module clear that
cache, and the next request imports the tasks of the new sets. After editing a
site's :file:`config.yaml` by hand, flush the caches.
