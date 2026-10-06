..  _installation:

============
Installation
============

..  _requirements:

Requirements
============

..  list-table::
    :header-rows: 1

    *   - Dependency
        - Version
    *   - PHP
        - 8.2, 8.3, 8.4
    *   - TYPO3
        - 13.4 LTS, 14.3 LTS
    *   - System extension
        - :composer:`typo3/cms-scheduler`

..  _installation-composer:

Composer installation
=====================

..  code-block:: bash

    composer require maikschneider/scheduler-as-code

The extension adds columns to :sql:`tx_scheduler_task`. Create them with
:bash:`extension:setup`, or with the database analyzer in the Install Tool:

..  code-block:: bash

    vendor/bin/typo3 extension:setup

There is nothing else to configure. Continue with :ref:`exporting your existing
tasks <usage-export>`.
