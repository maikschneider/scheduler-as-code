<?php

$EM_CONF[$_EXTKEY] = [
    'title' => 'Scheduler as Code',
    'description' => 'Manage TYPO3 scheduler tasks as YAML files in version control: export existing tasks, sync them into the database on deployment, and mark file-managed tasks in the Scheduler module.',
    'category' => 'be',
    'author' => 'Maik Schneider',
    'author_email' => 'schneider.maik@me.com',
    'state' => 'alpha',
    'version' => '0.1.0',
    'constraints' => [
        'depends' => [
            'typo3' => '13.4.0-14.99.99',
            'backend' => '13.4.0-14.99.99',
            'scheduler' => '13.4.0-14.99.99',
        ],
        'conflicts' => [],
        'suggests' => [],
    ],
];
