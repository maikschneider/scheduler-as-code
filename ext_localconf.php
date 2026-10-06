<?php

use TYPO3\CMS\Core\Cache\Backend\SimpleFileBackend;
use TYPO3\CMS\Core\Cache\Frontend\VariableFrontend;

defined('TYPO3') or die();

$GLOBALS['TYPO3_CONF_VARS']['SYS']['caching']['cacheConfigurations']['scheduler_as_code'] ??= [
    'frontend' => VariableFrontend::class,
    'backend' => SimpleFileBackend::class,
    'groups' => ['system'],
];
