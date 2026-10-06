<?php

use MaikSchneider\SchedulerAsCode\Middleware\SchedulerModuleBadges;

return [
    'backend' => [
        'maikschneider/scheduler-as-code/scheduler-module-badges' => [
            'target' => SchedulerModuleBadges::class,
            'after' => [
                'typo3/cms-backend/authentication',
            ],
        ],
    ],
];
